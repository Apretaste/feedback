<?php

use Apretaste\Person;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Database;

class Service {
	private $CREDITS_X_APPROVED = 5;
	private $CREDITS_X_VOTE = 0.5;
	private $MAX_VOTES_X_USER = 5;

	/**
	 * Function executed when the service is called
	 *
	 * @param Request             $request
	 * @param \Apretaste\Response $response
	 *
	 * @return void
	 * @throws \Framework\Alert
	 */
	public function _main(Request $request, Response $response) {
		$this->getMainResponse($response, 'Sugerencias abiertas', 'No hay sugerencias registradas', $request, 20, 'NEW');
	}

	/**
	 * Common response for some requests
	 *
	 * @param \Apretaste\Response $response
	 * @param string              $subject
	 * @param string              $no_subject
	 * @param \Apretaste\Request  $request
	 * @param int                 $limit
	 * @param string              $status
	 * @param string              $order
	 *
	 * @param string              $tpl
	 *
	 * @throws \Framework\Alert
	 */
	private function getMainResponse(Response $response, $subject, $no_subject, Request $request, $limit = 20, $status = 'NEW', $order = 'votes_count DESC', $tpl = 'list.ejs') {
		// discard suggestions that run out of time
		Database::query("UPDATE _sugerencias_list SET status='DISCARDED', updated=CURRENT_TIMESTAMP WHERE limit_date<=CURRENT_TIMESTAMP AND status='NEW'");

		//TODO: send notification here 

		// get list of tickets
		$tickets = Database::query("SELECT *, (select username from person where person.id = _sugerencias_list.person_id) as username FROM _sugerencias_list WHERE status='$status' ORDER BY $order ".($limit > -1 ? ' LIMIT 0, 20':''));

		// if not suggestion is registered
		if (empty($tickets)) {
			$message = 'Actualmente no hay registrada ninguna sugerencia. A&ntilde;ada la primera sugerencia usando el bot&oacute;n de abajo.';
			$response->setTemplate('fail.ejs', [
					'titulo'     => $no_subject,
					'mensaje'    => $message,
					'buttonNew'  => true,
					'buttonList' => false
			]);

			return;
		}

		// check if vote button should be enabled
		$availableVotes = $this->getAvailableVotes($request->person->id);
		$voteButtonEnabled = $availableVotes > 0;

		// create response array
		$responseContent = [
				'subject'           => $subject,
				'tickets'           => $tickets,
				'votosDisp'         => $availableVotes,
				'voteButtonEnabled' => $voteButtonEnabled
		];

		//TODO: create paging

		// return response object
		$response->setTemplate($tpl, $responseContent);
	}

	/**
	 * Sub-service ver, Display a full ticket
	 *
	 * @param \Apretaste\Request $request
	 *
	 * @throws \Framework\Alert
	 */
	public function _crear(Request $request, Response $response) {

		if (!isset($request->input->data->query))
			$request->input->data->query = '';

		if ($request->input->data->query === '') {
			$response->setTemplate('create.ejs');
			return;
		}

		// do not post short suggestions
		if (strlen($request->input->data->query) <= 10) {
			$mensaje = 'Esta sugerencia no se entiende. Por favor escribe una idea v&aacute;lida, puedes a&ntilde;adir una usando el boton de abajo.';
			$response->setTemplate('fail.ejs', [
					'titulo'     => 'Sugerencia no v&aacute;lida.',
					'mensaje'    => $mensaje,
					'buttonNew'  => true,
					'buttonList' => false
			]);

			return;
		}

		// get the deadline to discard the suggestion
		$fecha = new DateTime();
		$deadline = $fecha->modify('+15 days')->format('Y-m-d H:i:s');

		// get the number of votes to approved the suggestion
		$result = Database::query('SELECT COUNT(email) AS nbr FROM person WHERE active=1');
		$limitVotes = ceil($result[0]->nbr * 0.01);

		// insert a new suggestion
		$id = Database::query("
			INSERT INTO _sugerencias_list (`person_id`, `text`, `limit_votes`, `limit_date`)
			VALUES ('{$request->person->id}', '{$request->input->data->query}', '$limitVotes', '$deadline')");

		// create response
		$mensaje = "Su sugerencia ha sido registrada satisfactoriamente. Ya est&aacute; visible en la lista de sugerencias para que todos puedan votar por ella. Cada usuario (incluido usted) podr&aacute; votar, y si llega a sumar {$limitVotes} votos o m&aacute;s en un plazo de 15 d&iacute;as, ser&aacute; aprobada y todos ganar&aacute;n cr&eacute;ditos.";

		$response->setTemplate('success.ejs', [
			'titulo' => 'Sugerencia recibida',
			'mensaje' => $mensaje
		]);
	}

	/**
	 * Sub-service ver, Display a full ticket
	 *
	 * @param \Apretaste\Request $request
	 *
	 * @return \Apretaste\Response|void
	 * @throws \Framework\Alert
	 */
	public function _ver(Request $request, Response $response) {
		// get the suggestion
		$suggestion = Database::query("SELECT *, (SELECT username FROM person where person.id = suggestion.person_id) as username FROM _sugerencias_list WHERE id='{$request->input->data->id}'");

		if (empty($suggestion)) {
			return;
		}

		$suggestion = $suggestion[0];

		// get the username who created the suggestion
		$user = Person::find($suggestion->person_id);

		// check if vote button should be enabled
		$votosDisp = $this->getAvailableVotes($request->person->id);
		$voteButtonEnabled = $votosDisp > 0 && $suggestion->status==='NEW';

		// translate the status varible
		if ($suggestion->status==='NEW') $suggestion->estado = 'PENDIENTE';
		if ($suggestion->status==='APPROVED') $suggestion->estado = 'APROVADA';
		if ($suggestion->status==='DISCARDED') $suggestion->estado = 'RECHAZADA';

		// return response object
		$response->setTemplate('suggestion.ejs', [
				'suggestion'        => $suggestion,
				'voteButtonEnabled' => $voteButtonEnabled
		]);
	}

	/**
	 * Sub-service votar
	 *
	 * @param \Apretaste\Request $request
	 *
	 * @throws \Framework\Alert
	 */
	public function _votar(Request $request, Response $response) {

		// do not let pass without ID, and get the suggestion for later
		$suggestion = Database::query("SELECT `person_id`, votes_count, limit_votes FROM _sugerencias_list WHERE id={$request->input->data->id}");
		if (empty($suggestion)) {
			return;
		}

		$suggestion = $suggestion[0];

		// check you have enough available votes
		$votosDisp = $this->getAvailableVotes($request->person->id);
		if ($votosDisp <= 0) {
			$mensaje = 'No tienes ning&uacute;n voto disponible. Debes esperar a que sean aprobadas o descartadas las sugerencias por las que votaste para poder votar por alg&uacute;na otra. Mientras tanto, puedes ver la lista de sugerencias disponibles o escribir una nueva sugerencia.';
			$response->setTemplate('fail.ejs', [
					'titulo'     => 'No puedes votar por ahora.',
					'mensaje'    => $mensaje,
					'buttonNew'  => true,
					'buttonList' => true
			]);

			return;
		}

		// check if the user already voted for that idea
		$res = Database::query("SELECT COUNT(id) as nbr FROM _sugerencias_votes WHERE person_id='{$request->person->id}' AND feedback='{$request->input->data->id}'");
		if ($res[0]->nbr > 0) {
			$mensaje = 'No puedes votar dos veces por la misma sugerencia. Puedes seleccionar otra de la lista de sugerencias disponibles o escribir una nueva sugerencia.';
			$response->setTemplate('fail.ejs', [
					'titulo'     => 'Ya votastes por esta idea',
					'mensaje'    => $mensaje,
					'buttonNew'  => true,
					'buttonList' => true
			]);

			return;
		}

		// aqui inserto el voto y aumento el contador
		Database::query("
			INSERT INTO _sugerencias_votes (`person_id`, feedback) VALUES ('{$request->person->id}', '{$request->input->data->id}');
			UPDATE _sugerencias_list SET votes_count=votes_count+1 WHERE id={$request->input->data->id};");

		// check if the idea reached the number of votes to be approved
		if ($suggestion->votes_count + 1 >= $suggestion->limit_votes) {
			// asign credits to the creator and send a notification
			Database::query("UPDATE person SET credit=credit+{$this->CREDITS_X_APPROVED} WHERE email='{$suggestion->person_id}'");

			$msg = "Una sugerencia suya ha sido aprobada y usted gano §{$this->CREDITS_X_APPROVED}. Gracias!";
			Notifications::alert($request->person->id, $msg, '', '{command: "SUGERENCIAS VER",data:{query: "'.$request->input->data->query.'"}}');

			//$this->utils->addNotification($suggestion->user, 'Sugerencias', $msg, );

			// get all the people who voted for the suggestion
			$voters = Database::query("SELECT `person_id`, feedback FROM `_sugerencias_votes` WHERE `feedback` = {$request->input->data->id}");

			// asign credits to the voters and send a notification
			$longQuery = '';
			foreach ($voters as $voter) {
				$longQuery .= "UPDATE person SET credit=credit+{$this->CREDITS_X_VOTE} WHERE email='{$voter->person_id}';";
				$msg = "Usted voto por una sugerencia que ha sido aprobada y por lo tanto gano §{$this->CREDITS_X_VOTE}";
				Notifications::alert($request->person->id, $msg, '', '{command: "SUGERENCIAS VER",data:{query: "'.$voter->feedback.'"}}');
				//$this->utils->addNotification($voter->user, 'Sugerencias', $msg, "SUGERENCIAS VER {$voter->feedback}");
			}

			Database::query($longQuery);

			// mark suggestion as approved
			Database::query("UPDATE _sugerencias_list SET status='APPROVED', updated=CURRENT_TIMESTAMP WHERE id={$request->input->data->id}");
		}

		// create message to send to the user
		$votosDisp--;
		if ($votosDisp > 0) {
			$aux = "A&uacute;n le queda(n) $votosDisp voto(s) disponible(s). Si lo desea, puede votar por otra sugerencia de la lista.";
		} else {
			$aux = 'Ya no tiene ning&uacute;n voto disponible. Ahora debe esperar a que sean aprobadas o descartadas las sugerencias por las cuales vot&oacute; para poder votar por alg&uacute;na otra.';
		}
		$mensaje = "Su voto ha sido registrado satisfactoriamente. $aux";

		// send response object
		$response->setTemplate('success.ejs', ['titulo' => 'Voto enviado', 'mensaje' => $mensaje]);
	}

	/**
	 * Read the rules of the game
	 *
	 * @param \Apretaste\Request  $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _reglas(Request $request, Response $response) {
		$response->setTemplate('rules.ejs', []);
	}

	/**
	 * Return all suggestions
	 *
	 * @param \Apretaste\Request  $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _todas(Request $request, Response $response) {
		$this->getMainResponse($response, 'Todas las sugerencias recibidas', 'No hay sugerencias registradas', $request, -1);
	}

	/**
	 * Subservice aprobadas
	 *
	 * @param \Apretaste\Request  $request
	 * @param \Apretaste\Response $response
	 *
	 * @return void
	 * @throws \Framework\Alert
	 */
	public function _aprobadas(Request $request, Response $response) {
		$this->getMainResponse($response, 'Lista de sugerencias aprobadas', 'No hay sugerencias aprobadas', $request, -1, 'APPROVED', 'updated DESC', 'approved.ejs');
	}

	/**
	 * verify quantity of avaiable votes
	 *
	 * @param $personId
	 *
	 * @return int
	 * @throws \Framework\Alert
	 */
	private function getAvailableVotes($personId) {
		$res = Database::query("SELECT COUNT(*) as nbr FROM _sugerencias_votes WHERE person_id = '$personId'");

		return $this->MAX_VOTES_X_USER - $res[0]->nbr;
	}
}
