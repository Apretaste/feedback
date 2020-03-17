<?php

use Apretaste\Notifications;
use Apretaste\Person;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Database;

class Service
{
	private $CREDITS_X_APPROVED = 5;
	private $CREDITS_X_VOTE = 0.5;
	private $MAX_VOTES_X_USER = 5;

	/**Esto parece un servicio hecho corriendo, sorry pero hace falta que NO HAGAS LAS COSAS CORRIENDO Y TE FIJES EN LO QUE HACES 😞

D, [15.03.20 14:21]
Me ha tomado 3h revisar tu codigo. Realmente hubiera sido mas rapido hacerlo yo. Cuando no trabajas bien, me haces perder mi tiempo y pierdes el tuyo. Por favor haz las cosas con calidad y no trabajes corriendo para matar tareas y cumplir objetivos. Si crees que algo no saldra a tiempo dime y nos ponemos de acuerdo en los objetivos, pero no hagas mas las cosas corriendo.
	 * Function executed when the service is called
	 *
	 * @param Request  $request
	 * @param Response $response
	 * @return void
	 */
	public function _main(Request $request, Response $response) {
		$this->getMainResponse($response, '', 'No hay sugerencias registradas', $request, 20, 'NEW');
	}

	/**
	 * Common response for some requests
	 *
	 * @param  Response  $response
	 * @param  string  $subject
	 * @param  string  $no_subject
	 * @param  Request  $request
	 * @param  int  $limit
	 * @param  string  $status
	 * @param  string  $order
	 * @param  string  $tpl
	 *
	 * @throws \Framework\Alert
	 */
	private function getMainResponse(Response $response, $subject, $no_subject, Request $request, $limit = 20, $status = 'NEW', $order = 'votes_count DESC', $tpl = 'list.ejs')
	{
		// discard suggestions that run out of time
		Database::query("
			UPDATE _sugerencias_list 
			SET status='DISCARDED', updated=CURRENT_TIMESTAMP 
			WHERE limit_date <= CURRENT_TIMESTAMP 
			AND status = 'NEW'");

		// get list of tickets
		$limitSQL = $limit > -1 ? ' LIMIT 0, 20' : '';
		$tickets = Database::query("
			SELECT A.*, B.username, B.avatar, B.avatarColor
			FROM _sugerencias_list A
			INNER JOIN person B
			ON A.person_id = B.id
			WHERE status = '$status' 
			ORDER BY $order 
			$limitSQL");

		// if not suggestion is registered
		if (empty($tickets)) {
			$message = 'Actualmente no hay registrada ninguna sugerencia. Añada la primera sugerencia usando el botón de abajo.';
			$response->setTemplate('fail.ejs', [
				'titulo'  => $no_subject,
				'mensaje' => $message,
				'buttonNew' => true,
				'buttonList' => false
			]);
			return;
		}

		// check if vote button should be enabled
		$availableVotes = $this->getAvailableVotes($request->person->id);
		$voteButtonEnabled = $availableVotes > 0;

		// create response array
		$responseContent = [
			'subject' => $subject,
			'tickets' => $tickets,
			'votosDisp' => $availableVotes,
			'voteButtonEnabled' => $voteButtonEnabled
		];

		// return response object
		$response->setCache('hour');
		$response->setTemplate($tpl, $responseContent);
	}

	/**
	 * Sub-service ver, Display a full ticket
	 *
	 * @param Request $request
	 *
	 */
	public function _crear(Request $request, Response $response)
	{
		if (!isset($request->input->data->query))
			$request->input->data->query = '';

		// do not post short suggestions
		if (strlen($request->input->data->query) <= 10) {
			$response->setTemplate('fail.ejs', [
				'titulo'  => 'Sugerencia no válida.',
				'mensaje' => 'Esta sugerencia no se entiende. Por favor escribe una idea válida, puedes añadir una usando el boton de abajo.',
				'buttonNew' => true,
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
		$mensaje = "Su sugerencia ha sido registrada satisfactoriamente. Ya está visible en la lista de sugerencias para que todos puedan votar por ella. Cada usuario (incluido usted) podrá votar, y si llega a sumar {$limitVotes} votos o más en un plazo de 15 días, será aprobada y todos ganarán cr&eacute;ditos.";

		$response->setTemplate('success.ejs', [
			'titulo' => 'Sugerencia recibida',
			'mensaje' => $mensaje
		]);
	}

	/**
	 * Display a full ticket
	 *
	 * @param Request $request
	 * @return Response|void
	 */
	public function _ver(Request $request, Response $response)
	{
		// get the suggestion
		$suggestion = Database::query("
			SELECT *, (SELECT username FROM person WHERE person.id = _sugerencias_list.person_id) AS username 
			FROM _sugerencias_list 
			WHERE id = '{$request->input->data->id}'");

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
			'suggestion' => $suggestion,
			'voteButtonEnabled' => $voteButtonEnabled
		]);
	}

	/**
	 * Sub-service votar
	 *
	 * @param Request $request
	 *
	 * @throws \Framework\Alert
	 */
	public function _votar(Request $request, Response $response)
	{
		// do not let pass without ID, and get the suggestion for later
		$suggestion = Database::query("SELECT `person_id`, votes_count, limit_votes FROM _sugerencias_list WHERE id={$request->input->data->id}");
		if (empty($suggestion)) {
			return;
		}

		$suggestion = $suggestion[0];

		// check you have enough available votes
		$votosDisp = $this->getAvailableVotes($request->person->id);
		if ($votosDisp <= 0) {
			$response->setTemplate('fail.ejs', [
				'titulo'  => 'No puedes votar por ahora.',
				'mensaje' => 'No tienes ningún voto disponible. Debes esperar a que sean aprobadas o descartadas las sugerencias por las que votaste para poder votar por algúna otra. Mientras tanto, puedes ver la lista de sugerencias disponibles o escribir una nueva sugerencia.',
				'buttonNew' => true,
				'buttonList' => true
			]);
			return;
		}

		// check if the user already voted for that idea
		$res = Database::query("SELECT COUNT(id) as nbr FROM _sugerencias_votes WHERE person_id='{$request->person->id}' AND feedback='{$request->input->data->id}'");
		if ($res[0]->nbr > 0) {
			$mensaje = 'No puedes votar dos veces por la misma sugerencia. Puedes seleccionar otra de la lista de sugerencias disponibles o escribir una nueva sugerencia.';
			$response->setTemplate('message.ejs', [
				"header" => "Votación fallida",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "Ya habías votado por esa sugerencia. $mensaje",
				"button" => ["href" => "SUGERENCIAS", "caption" => "Ver sugerencias"]
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
			}

			Database::query($longQuery);

			// mark suggestion as approved
			Database::query("UPDATE _sugerencias_list SET status='APPROVED', updated=CURRENT_TIMESTAMP WHERE id={$request->input->data->id}");
		}

		// create message to send to the user
		$votosDisp--;
		if ($votosDisp > 0) {
			$aux = "Aún le queda(n) $votosDisp voto(s) disponible(s). Si lo desea, puede votar por otra sugerencia de la lista.";
		} else {
			$aux = 'Ya no tiene ningún voto disponible. Ahora debe esperar a que sean aprobadas o descartadas las sugerencias por las cuales votó para poder votar por algúna otra.';
		}
		$mensaje = "Su voto ha sido registrado satisfactoriamente. $aux";

		// send response object
		$response->setTemplate('message.ejs', [
			"header" => "Voto enviado",
			"icon" => "sentiment_very_satisfied",
			"text" => "Su voyo ha sido guardado satisfactoriamente",
			"button" => ["href" => "SUGERENCIAS", "caption" => "Ver sugerencias"]
		]);
		//$response->setTemplate('success.ejs', ['titulo' => 'Voto enviado', 'mensaje' => $mensaje]);
	}

	/**
	 * Read the rules of the game
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _reglas(Request $request, Response $response)
	{
		$response->setCache("year");
		$response->setTemplate('rules.ejs', []);
	}

	/**
	 * Return all suggestions
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _todas(Request $request, Response $response)
	{
		$this->getMainResponse($response, 'Todas las sugerencias recibidas', 'No hay sugerencias registradas', $request, -1);
	}

	/**
	 * Subservice aprobadas
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return void
	 */
	public function _aprobadas(Request $request, Response $response)
	{
		$this->getMainResponse($response, 'Lista de sugerencias aprobadas', 'No hay sugerencias aprobadas', $request, -1, 'APPROVED', 'updated DESC', 'approved.ejs');
	}

	/**
	 * Verify how many votes are avaiable
	 *
	 * @param $personId
	 * @return int
	 */
	private function getAvailableVotes($personId) {
		$res = Database::query("SELECT COUNT(*) AS nbr FROM _sugerencias_votes WHERE person_id = '$personId'");
		return $this->MAX_VOTES_X_USER - $res[0]->nbr;
	}
}
