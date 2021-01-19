<?php

use Apretaste\Money;
use Apretaste\Request;
use Apretaste\Response;
use Apretaste\Tutorial;
use Apretaste\Notifications;
use Framework\Database;

class Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 */
	public function _main(Request $request, Response $response)
	{
		// filter by page number
		$page = $request->input->data->page ?? 0;
		if($page < 0) $page = 0;
		$offset = $page * 20;

		// get list of tickets
		$tickets = Database::query("
			SELECT A.id, A.text, A.votes_count, A.limit_votes, A.limit_date, B.username, B.avatar, B.avatarColor, B.gender, B.online 
			FROM _sugerencias_list A 
			INNER JOIN person B ON A.person_id = B.id 
			WHERE A.limit_date > CURRENT_TIMESTAMP
			AND A.votes_count < A.limit_votes
			AND A.status = 'NEW'
			ORDER BY A.votes_count DESC
			LIMIT 20 OFFSET $offset");

		// calculate percentages
		foreach ($tickets as $ticket) {
			$ticket->percent = floor(($ticket->votes_count * 100) / $ticket->limit_votes);
			$dots = mb_strlen($ticket->text) > 80 ? '...' : '';
			$ticket->text = trim(mb_substr($ticket->text, 0, 80)) . $dots;
		}

		// calculate the total number of pages
		$rowsCount = Database::query("SELECT COUNT(id) AS cnt FROM _sugerencias_list A WHERE A.status = 'NEW' AND A.limit_date > CURRENT_TIMESTAMP")[0]->cnt;
		$pagesCount = ceil($rowsCount / 20);

		// check if vote button can be enabled
		$canVote = Database::queryFirst("
			SELECT COUNT(id) AS cnt 
			FROM _sugerencias_votes 
			WHERE person_id = {$request->person->id} 
			AND vote_date >= CURRENT_DATE")->cnt == 0;

		// create response array
		$content = [
			'tickets' => $tickets,
			'canVote' => $canVote,
			'page' => $page,
			'pages' => $pagesCount];

		// return response object
		$response->setCache('hour');
		$response->setTemplate('list.ejs', $content);
	}

	/**
	 * Display a full ticket
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _ver(Request $request, Response $response)
	{
		// get the id, or nullify the query
		$id = $request->input->data->id ?? 0;

		// get the suggestion
		$suggestion = Database::queryFirst("
			SELECT 
				A.id, A.text, A.votes_count, A.limit_votes, A.limit_date, 
				A.status, B.username, B.avatar, B.avatarColor, B.gender, 
				B.online, B.first_name, B.experience, B.week_rank
			FROM _sugerencias_list A 
			INNER JOIN person B 
			ON A.person_id = B.id
			WHERE A.id = $id");

		// do not continue if not ID was passed
		if (empty($suggestion)) {
			return $response->setTemplate('message.ejs', [
				'header' => 'Sugerencia no válida.',
				'icon' => 'sentiment_dissatisfied',
				'text' => 'No pudimos encontrar esta sugerencia. Es posible que esto sea un error, por favor intente nuevamente.',
				'btnLink' => 'SUGERENCIAS',
				'btnCaption' => 'Ver sugerencias'
			]);
		}

		// calculate percentage
		$suggestion->percent = floor(($suggestion->votes_count * 100) / $suggestion->limit_votes);

		// calculate label status
		if($suggestion->status == "APPROVED") $suggestion->status = "Aceptada";
		elseif($suggestion->status == "DISCARDED") $suggestion->status = "Rechazada";
		else $suggestion->status = "Abierta";

		// check if vote button can be enabled
		$canVote = Database::queryFirst("
			SELECT COUNT(id) AS cnt 
			FROM _sugerencias_votes 
			WHERE person_id = {$request->person->id} 
			AND vote_date >= CURRENT_DATE")->cnt == 0;

		// return response object
		$response->setCache('hour');
		$response->setTemplate('view.ejs', ['item'=>$suggestion, 'canVote'=>$canVote]);
	}

	/**
	 * Search for a group of suggestions
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _buscar(Request $request, Response $response)
	{
		$response->setCache('year');
		$response->setTemplate('search.ejs', ['username'=>$request->person->username]);
	}

	/**
	 * Process a search
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _encontrar(Request $request, Response $response)
	{
		// get search values
		$username = empty($request->input->data->username) ? '' : str_replace('@', '', $request->input->data->username);
		$status = empty($request->input->data->status) ? '' : $request->input->data->status;
		$text = empty($request->input->data->text) ? '' : $request->input->data->text;

		$username = Database::escape($username);
		$status = Database::escape($status);
		$text = Database::escape($text);

		// get list of tickets
		$tickets = Database::query("
			SELECT A.id, A.text, A.votes_count, A.limit_votes, A.limit_date, B.username, B.avatar, B.avatarColor, B.gender, B.online 
			FROM _sugerencias_list A 
			INNER JOIN person B ON A.person_id = B.id 
			WHERE B.username LIKE '%$username%'
			AND A.text LIKE '%$text%'
			AND A.status LIKE '%$status%'
			ORDER BY A.votes_count DESC
			LIMIT 20");

		// if not suggestion is registered
		if (empty($tickets)) {
			return $response->setTemplate('message.ejs', [
				'header' => 'No hay sugerencias',
				'icon' => 'sentiment_dissatisfied',
				'text' => 'No encontramos ninguna sugerencia que responda a dicha búsqueda. Por favor cambie sus parámetros de búsqueda e intente nuevamente.',
				'btnLink' => 'SUGERENCIAS BUSCAR',
				'btnCaption' => 'Buscar sugerencias'
			]);
		}

		// calculate percentages
		foreach ($tickets as $ticket) {
			$ticket->percent = floor(($ticket->votes_count * 100) / $ticket->limit_votes);
			$dots = mb_strlen($ticket->text) > 80 ? '...' : '';
			$ticket->text = trim(mb_substr($ticket->text, 0, 80)) . $dots;
		}

		// return response object
		$response->setCache('hour');
		$response->setTemplate('find.ejs', ['tickets' => $tickets]);
	}

	/**
	 * Read the rules of the game
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _reglas(Request $request, Response $response)
	{
		$response->setCache('year');
		$response->setTemplate('rules.ejs', []);
	}

	/**
	 * Sub-service ver, Display a full ticket
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _crear(Request $request, Response $response)
	{
		// get the message
		$message = $request->input->data->message ?? "";

		// do not post short suggestions
		if (strlen($message) < 20) {
			return $response->setTemplate('message.ejs', [
				'header' => 'Sugerencia no válida.',
				'icon' => 'sentiment_dissatisfied',
				'text' => 'Esta sugerencia no se entiende. Por favor escribe una idea que tenga más de 20 caracteres y menos de 300',
				'btnLink' => 'SUGERENCIAS',
				'btnCaption' => 'Ver sugerencias'
			]);
		}

		// get the deadline to discard the suggestion
		$fecha = new DateTime();
		$deadline = $fecha->modify('+15 days')->format('Y-m-d H:i:s');

		// get the number of votes to approve the suggestion
		$result = Database::queryCache("SELECT COUNT(id) AS cnt FROM person WHERE `status` = 'ACTIVE'", Database::CACHE_DAY);
		$limitVotes = ceil($result[0]->cnt * 0.05);
		if(empty($limitVotes)) $limitVotes = 10;

		// escape message
		$message = Database::escape($message);

		// insert a new suggestion
		Database::query("
			INSERT INTO _sugerencias_list (person_id, `text`, limit_votes, limit_date) 
			VALUES ({$request->person->id}, '$message', '$limitVotes', '$deadline')");

		// complete tutorial
		Tutorial::complete($request->person->id, 'leave_feedback');

		// create response
		$response->setTemplate('message.ejs', [
			'header' => 'Sugerencia recibida',
			'icon' => 'thumb_up',
			'text' => "Su sugerencia ha sido registrada satisfactoriamente y ya está visible para que otros voten. Cada usuario (incluido usted) podrá votar, y si llega a sumar {$limitVotes} votos en 15 días, será aprobada y todos ganarán créditos.",
			'btnLink' => 'SUGERENCIAS',
			'btnCaption' => 'Ver sugerencias'
		]);
	}

	/**
	 * Sub-service votar
	 *
	 * @param Request $request
	 */
	public function _votar(Request $request, Response $response)
	{
		// get the id, or nullify the query
		$id = $request->input->data->id ?? 0;

		// get the suggestion
		$suggestion = Database::queryFirst("SELECT * FROM _sugerencias_list WHERE id = $id");

		// check if user already voted today
		$alreadyVoted = Database::queryFirst("
			SELECT COUNT(id) AS cnt 
			FROM _sugerencias_votes 
			WHERE person_id = {$request->person->id}
			AND vote_date > CURRENT_DATE")->cnt > 0;

		// do not continue if not ID was passed
		if (empty($suggestion) || $alreadyVoted) {
			return $response->setTemplate('message.ejs', [
				'header' => 'Votación fallida',
				'icon' => 'sentiment_dissatisfied',
				'text' => "Hemos encontrado un error en la votacion, por favor busque dicha sugerencia e intente nuevamente",
				'btnLink' => 'SUGERENCIAS',
				'btnCaption' => 'Ver sugerencias'
			]);
		}

		// create vote and increase suggestion counter
		Database::query("
			INSERT INTO _sugerencias_votes (person_id, feedback) VALUES ({$request->person->id}, $id);
			UPDATE _sugerencias_list SET votes_count = votes_count + 1 WHERE id = $id;");

		// send notification
		Notifications::alert($suggestion->person_id, "El usuario @{$request->person->username} ha votado por tu sugerencia", '', '{command:"SUGERENCIAS VER", data:{query:"'.$id.'"}}');

		// send response object
		$response->setTemplate('message.ejs', [
			'header' => 'Voto enviado',
			'icon' => 'thumb_up',
			'text' => "Su voto ha sido registrado satisfactoriamente. Mañana tendrá otro voto disponible. ¡Gracias por ayudarnos a mejorar Apretaste!",
			'btnLink' => 'SUGERENCIAS ' . rand(),
			'btnCaption' => 'Ver sugerencias'
		]);
	}
}
