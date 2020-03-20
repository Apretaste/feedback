<?php

use Apretaste\Money;
use Apretaste\Notifications;
use Apretaste\Person;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Database;

class Service
{

	private int $CREDITS_X_APPROVED = 5;
	private float $CREDITS_X_VOTE = 0.5;
	private int $MAX_VOTES_X_USER = 5;

	/**
	 * @throws \Framework\Alert
	 */
	private function discardSuggestions() {
		// discard suggestions that run out of time
		Database::query("UPDATE _sugerencias_list SET status='DISCARDED', updated=CURRENT_TIMESTAMP WHERE limit_date <= CURRENT_TIMESTAMP AND status = 'NEW'");
	}

	/**
	 * Function executed when the service is called
	 *
	 * @param  Request  $request
	 * @param  Response  $response
	 *
	 * @return void
	 * @throws \Framework\Alert
	 */
	public function _main(Request $request, Response $response) {

		$this->discardSuggestions();

		// get list of tickets
		$tickets = Database::query("SELECT A.*, B.username, B.avatar, B.avatarColor FROM _sugerencias_list A INNER JOIN person B ON A.person_id = B.id WHERE status = 'NEW' ORDER BY votes_count DESC LIMIT 0, 20");

		// if not suggestion is registered
		if (empty($tickets)) {
			$response->setTemplate('message.ejs', [
			  'header' => 'No hay sugerencias registradas',
			  'icon' => 'Actualmente no hay registrada ninguna sugerencia. Añada la primera sugerencia usando el botón de abajo.',
			  'newButton' => true,
			  'button' => ['href' => 'SUGERENCIAS', 'caption' => 'Ver sugerencias']
			]);
			return;
		}

		// check if vote button should be enabled
		$availableVotes = $this->getAvailableVotes($request->person->id);
		$voteButtonEnabled = $availableVotes > 0;

		// create response array
		$responseContent = [
		  'subject' => '',
		  'tickets' => $tickets,
		  'votosDisp' => $availableVotes,
		  'voteButtonEnabled' => $voteButtonEnabled
		];

		// return response object
		//$response->setCache('hour');
		$response->setTemplate('list.ejs', $responseContent);
	}

	/**
	 * Sub-service ver, Display a full ticket
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws \Framework\Alert
	 */
	public function _crear(Request $request, Response $response)
	{
		if (!isset($request->input->data->query))
			$request->input->data->query = '';

		// do not post short suggestions
		if (strlen($request->input->data->query) <= 10) {
			$response->setTemplate('message.ejs', [
			  'header' => 'Sugerencia no válida.',
			  'icon' => 'Esta sugerencia no se entiende. Por favor escribe una idea válida, puedes añadir una usando el boton de abajo.',
			  'newButton' => true,
			  'button' => ['href' => 'SUGERENCIAS', 'caption' => 'Ver sugerencias']
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
		$mensaje = "Su sugerencia ha sido registrada satisfactoriamente. Ya está visible en la lista de sugerencias para que todos puedan votar por ella. Cada usuario (incluido usted) podrá votar, y si llega a sumar {$limitVotes} votos o más en un plazo de 15 días, será aprobada y todos ganarán créditos.";

		$response->setTemplate('message.ejs', [
		  'header' => 'Sugerencia recibida',
		  'icon' => 'sentiment_very_satisfied',
		  'text' => $mensaje,
		  'button' => ['href' => 'SUGERENCIAS', 'caption' => 'Ver sugerencias']
		]);
	}

	/**
	 * Display a full ticket
	 *
	 * @param  Request  $request
	 *
	 * @return Response|void
	 * @throws \Framework\Alert
	 */
	public function _ver(Request $request, Response $response)
	{
		// get the suggestion
		$suggestion = Database::query("
			SELECT _sugerencias_list.*, person.username, person.avatar, person.avatarColor 
			FROM _sugerencias_list inner join person on person.id =  _sugerencias_list.person_id
			WHERE _sugerencias_list.id = '{$request->input->data->id}'");

		if (empty($suggestion)) {
			return;
		}

		$suggestion = $suggestion[0];

		// check if vote button should be enabled
		$availableVotes = $this->getAvailableVotes($request->person->id);
		$voteButtonEnabled = $availableVotes > 0 && $suggestion->status==='NEW';

		// translate the status varible
		if ($suggestion->status==='NEW') $suggestion->estado = 'PENDIENTE';
		if ($suggestion->status==='APPROVED') $suggestion->estado = 'APROVADA';
		if ($suggestion->status==='DISCARDED') $suggestion->estado = 'RECHAZADA';

		$response->setTemplate('suggestion.ejs', [
			'suggestion' => $suggestion,
			'voteButtonEnabled' => $voteButtonEnabled,
		  	'votosDisp' => $availableVotes
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
			$response->setTemplate('message.ejs', [
			  'header' => 'No puedes votar por ahora.',
			  'icon' => 'sentiment_very_unssatisfied',
			  'text' => 'No tienes ningún voto disponible. Debes esperar a que sean aprobadas o descartadas las sugerencias por las que votaste para poder votar por algúna otra. Mientras tanto, puedes ver la lista de sugerencias disponibles o escribir una nueva sugerencia.',
			  'button' => ['href' => 'SUGERENCIAS', 'caption' => 'Ver sugerencias']
			]);
			return;
		}

		// check if the user already voted for that idea
		$res = Database::query("SELECT COUNT(id) as nbr FROM _sugerencias_votes WHERE person_id='{$request->person->id}' AND feedback='{$request->input->data->id}'");
		if ($res[0]->nbr > 0) {
			$mensaje = 'No puedes votar dos veces por la misma sugerencia. Puedes seleccionar otra de la lista de sugerencias disponibles o escribir una nueva sugerencia.';
			$response->setTemplate('message.ejs', [
				'header' => 'Votación fallida',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => "Ya habías votado por esa sugerencia. $mensaje",
				'button' => ['href' => 'SUGERENCIAS', 'caption' => 'Ver sugerencias']
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
			Money::send(Money::BANK, $suggestion->person_id, $this->CREDITS_X_APPROVED, 'sugerencia aprovada');

			$msg = "Una sugerencia suya ha sido aprobada y usted gano §{$this->CREDITS_X_APPROVED}. Gracias!";
			Notifications::alert($request->person->id, $msg, '', '{command: "SUGERENCIAS VER",data:{query: "'.$request->input->data->query.'"}}');

			// get all the people who voted for the suggestion
			$voters = Database::query("SELECT `person_id`, feedback FROM `_sugerencias_votes` WHERE `feedback` = {$request->input->data->id}");

			// asign credits to the voters and send a notification
			foreach ($voters as $voter) {
				Money::send(Money::BANK, $voter->person_id, $this->CREDITS_X_VOTE, "VOTO A SUGERENCIA APROBADA");

				$msg = "Usted voto por una sugerencia que ha sido aprobada y por lo tanto gano §{$this->CREDITS_X_VOTE}";
				Notifications::alert($request->person->id, $msg, '', '{command: "SUGERENCIAS VER",data:{query: "'.$voter->feedback.'"}}');
			}

			// mark suggestion as approved
			Database::query("UPDATE _sugerencias_list SET status='APPROVED', updated=CURRENT_TIMESTAMP WHERE id={$request->input->data->id}");
		}

		// create message to send to the user
		$votosDisp--;
		if ($votosDisp > 0) {
			$aux = "Le quedan $votosDisp votos. Cuando las ideas que apoyó ganan o pierdan, usted recuperará sus votos";
		} else {
			$aux = 'Ya no tiene ningún voto disponible. Ahora debe esperar a que sean aprobadas o descartadas las sugerencias por las cuales votó para poder votar por algúna otra.';
		}

		$mensaje = "Su voto ha sido registrado satisfactoriamente. $aux";

		// send response object
		$response->setTemplate('message.ejs', [
			'header' => 'Voto enviado',
			'icon' => 'sentiment_very_satisfied',
			'text' => $mensaje,
			'button' => ['href' => 'SUGERENCIAS', 'caption' => 'Ver sugerencias']
		]);

		Notifications::alert($suggestion->person_id, "El usuario @{$request->person->username} ha votado por tu sugerencia", '', '{command: "SUGERENCIAS VER",data:{query: "'.$request->input->data->id.'"}}');
	}

	/**
	 * Read the rules of the game
	 *
	 * @param  Request  $request
	 * @param  Response  $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _reglas(Request $request, Response $response)
	{
		$response->setCache('year');
		$response->setTemplate('rules.ejs', []);
	}

	/**
	 * Return all suggestions
	 *
	 * @param  Request  $request
	 * @param  Response  $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _todas(Request $request, Response $response)
	{
		$this->discardSuggestions();

		// get list of tickets
		$tickets = Database::query("SELECT A.*, B.username, B.avatar, B.avatarColor FROM _sugerencias_list A INNER JOIN person B ON A.person_id = B.id ORDER BY votes_count DESC");

		// if not suggestion is registered
		if (empty($tickets)) {
			$response->setTemplate('message.ejs', [
			  'header' => 'No hay sugerencias registradas',
			  'icon' => 'sentiment_very_unssatisfied',
			  'text' => 'Actualmente no hay registrada ninguna sugerencia. Añada la primera sugerencia usando el botón de abajo.',
			  'button' => ['href' => 'SUGERENCIAS', 'caption' => 'Ver sugerencias']
			]);
			return;
		}

		// check if vote button should be enabled
		$availableVotes = $this->getAvailableVotes($request->person->id);
		$voteButtonEnabled = $availableVotes > 0;

		// create response array
		$responseContent = [
			'subject' => 'Todas las sugerencias recibidas',
			'tickets' => $tickets,
			'votosDisp' => $availableVotes,
			'voteButtonEnabled' => $voteButtonEnabled
		];

		// return response object
		//$response->setCache('hour');
		$response->setTemplate("list.ejs", $responseContent);
	}

	/**
	 * Subservice aprobadas
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return void
	 * @throws \Framework\Alert
	 */
	public function _aprobadas(Request $request, Response $response)
	{
		$this->discardSuggestions();

		// get list of tickets
		$tickets = Database::query("SELECT A.*, B.username, B.avatar, B.avatarColor FROM _sugerencias_list A INNER JOIN person B ON A.person_id = B.id WHERE status = 'APPROVED' ORDER BY updated DESC LIMIT 0, 20");

		// if not suggestion is registered
		if (empty($tickets)) {
			$response->setTemplate('message.ejs', [
			  'header' => 'No hay sugerencias aprobadas',
			  'icon' => 'sentiment_very_unssatisfied',
			  'text' => 'Actualmente no hay registrada ninguna aprobada.',
			  'button' => ['href' => 'SUGERENCIAS', 'caption' => 'Ver sugerencias']
			]);
			return;
		}

		// check if vote button should be enabled
		$availableVotes = $this->getAvailableVotes($request->person->id);
		$voteButtonEnabled = $availableVotes > 0;

		// create response array
		$responseContent = [
			'subject' => 'Lista de sugerencias aprobadas',
			'tickets' => $tickets,
			'votosDisp' => $availableVotes,
			'voteButtonEnabled' => $voteButtonEnabled
		];

		// return response object
		//$response->setCache('hour');
		$response->setTemplate('approved.ejs', $responseContent);
	}

	/**
	 * Verify how many votes are avaiable
	 *
	 * @param $personId
	 * @return int
	 * @throws \Framework\Alert
	 */
	private function getAvailableVotes($personId) {
		$res = Database::query("SELECT COUNT(*) AS nbr FROM _sugerencias_votes WHERE person_id = '$personId'");
		return $this->MAX_VOTES_X_USER - $res[0]->nbr;
	}
}
