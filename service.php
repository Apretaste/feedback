<?php

class Sugerencias extends Service
{
	private $CREDITS_X_APPROVED = 5;
	private $CREDITS_X_VOTE = 0.5;
	private $MAX_VOTES_X_USER = 5;

	/**
	 * Function executed when the service is called
	 *
	 * @param Request $request
	 * @param int     $limit
	 *
	 * @return Response
	 */
	public function _main(Request $request, $limit = 20)
	{
		return $this->getMainResponse("Sugerencias abiertas", "No hay sugerencias registradas", $request, 20, 'NEW');
	}

	/**
	 * Common response for some requests
	 *
	 * @param string   $subject
	 * @param string   $no_subject
	 * @param \Request $request
	 * @param int      $limit
	 * @param string   $status
	 * @param string   $order
	 *
	 * @return \Response
	 */
	private function getMainResponse($subject, $no_subject, Request $request, $limit = 20, $status = 'NEW', $order = 'votes_count DESC', $tpl = 'list.tpl')
	{
		// discard suggestions that run out of time
		Connection::query("UPDATE _sugerencias_list SET status='DISCARDED', updated=CURRENT_TIMESTAMP WHERE limit_date<=CURRENT_TIMESTAMP AND status='NEW'");

		//TODO: send notification here 

		// get list of tickets
		$tickets = Connection::query("SELECT * FROM _sugerencias_list WHERE status='$status' ORDER BY $order " . ($limit > - 1 ? " LIMIT 0, 20" : ""));

		// if not suggestion is registered
		if(empty($tickets))
		{
			$response = new Response();
			$message  = "Actualmente no hay registrada ninguna sugerencia. A&ntilde;ada la primera sugerencia usando el bot&oacute;n de abajo.";
			$response->setResponseSubject("No hay ninguna sugerencia abierta todavia.");
			$response->createFromTemplate("fail.tpl", [
				"titulo" => $no_subject,
				"mensaje" => $message,
				"buttonNew" => true,
				"buttonList" => false
			]);

			return $response;
		}

		// check if vote button should be enabled
		$availableVotes    = $this->getAvailableVotes($request->email);
		$voteButtonEnabled = $availableVotes > 0;

		// get all the user names
		foreach($tickets as $ticket)
		{
			$ticket->username = $this->utils->getUsernameFromEmail($ticket->user);
		}

		// create response array
		$responseContent = [
			"subject" => $subject,
			"tickets" => $tickets,
			"votosDisp" => $availableVotes,
			"voteButtonEnabled" => $voteButtonEnabled
		];

		//TODO: create paging

		// return response object
		$response = new Response();
		$response->setResponseSubject($subject);
		$response->createFromTemplate($tpl, $responseContent);

		return $response;

	}

	/**
	 * Sub-service ver, Display a full ticket
	 *
	 * @param Request
	 *
	 * @return Response
	 */
	public function _crear(Request $request)
	{
		// do not post short suggestions
		if(strlen($request->query) <= 10)
		{
			$response = new Response();
			$mensaje  = "Esta sugerencia no se entiende. Por favor escribe una idea v&aacute;lida, puedes a&ntilde;adir una usando el boton de abajo.";
			$response->setResponseSubject("Sugerencia no valida.");
			$response->createFromTemplate("fail.tpl", [
				"titulo" => "Sugerencia no v&aacute;lida.",
				"mensaje" => $mensaje,
				"buttonNew" => true,
				"buttonList" => false
			]);

			return $response;
		}

		// get the deadline to discard the suggestion
		$fecha    = new DateTime();
		$deadline = $fecha->modify('+15 days')->format('Y-m-d H:i:s');

		// get the number of votes to approved the suggestion
		$result     = Connection::query("SELECT COUNT(email) AS nbr FROM person WHERE active=1");
		$limitVotes = ceil($result[0]->nbr * 0.01);

		// insert a new suggestion
		$id = Connection::query("
			INSERT INTO _sugerencias_list (`user`, `text`, `limit_votes`, `limit_date`)
			VALUES ('{$request->email}', '{$request->query}', '$limitVotes', '$deadline')");

		// create response
		$response = new Response();
		$mensaje  = "Su sugerencia ha sido registrada satisfactoriamente. Ya est&aacute; visible en la lista de sugerencias para que todos puedan votar por ella. Cada usuario (incluido usted) podr&aacute; votar, y si llega a sumar {$limitVotes} votos o m&aacute;s en un plazo de 15 d&iacute;as, ser&aacute; aprobada y todos ganar&aacute;n cr&eacute;ditos.";
		$response->setResponseSubject("Sugerencia recibida");
		$response->createFromTemplate("success.tpl", ["titulo" => "Sugerencia recibida", "mensaje" => $mensaje]);

		return $response;
	}

	/**
	 * Sub-service ver, Display a full ticket
	 *
	 * @param Request
	 *
	 * @return Response
	 */
	public function _ver(Request $request)
	{
		// get the suggestion
		$suggestion = Connection::query("SELECT * FROM _sugerencias_list WHERE id='{$request->query}'");
		if(empty($suggestion)) return new Response();
		else $suggestion = $suggestion[0];

		// get the username who created the suggestion
		$suggestion->username = $this->utils->getUsernameFromEmail($suggestion->user);

		// check if vote button should be enabled
		$votosDisp         = $this->getAvailableVotes($request->email);
		$voteButtonEnabled = $votosDisp > 0 && $suggestion->status == "NEW";

		// translate the status varible
		if($suggestion->status == "NEW") $suggestion->estado = "PENDIENTE";
		if($suggestion->status == "APPROVED") $suggestion->estado = "APROVADA";
		if($suggestion->status == "DISCARDED") $suggestion->estado = "RECHAZADA";

		// return response object
		$response = new Response();
		$response->setResponseSubject("Sugerencia #{$suggestion->id}");
		$response->createFromTemplate("suggestion.tpl", [
			"suggestion" => $suggestion,
			"voteButtonEnabled" => $voteButtonEnabled
		]);

		return $response;
	}

	/**
	 * Sub-service votar
	 *
	 * @param Request
	 *
	 * @return Response
	 */
	public function _votar(Request $request)
	{
		$response = new Response();

		// do not let pass without ID, and get the suggestion for later
		$suggestion = Connection::query("SELECT `user`, votes_count, limit_votes FROM _sugerencias_list WHERE id={$request->query}");
		if(empty($suggestion)) return $response;
		else $suggestion = $suggestion[0];

		// check you have enough available votes
		$votosDisp = $this->getAvailableVotes($request->email);
		if($votosDisp <= 0)
		{
			$mensaje = "No tienes ning&uacute;n voto disponible. Debes esperar a que sean aprobadas o descartadas las sugerencias por las que votaste para poder votar por alg&uacute;na otra. Mientras tanto, puedes ver la lista de sugerencias disponibles o escribir una nueva sugerencia.";
			$response->setResponseSubject("No puedes votar por ahora");
			$response->createFromTemplate("fail.tpl", [
				"titulo" => "No puedes votar por ahora.",
				"mensaje" => $mensaje,
				"buttonNew" => true,
				"buttonList" => true
			]);

			return $response;
		}

		// check if the user already voted for that idea
		$res = Connection::query("SELECT COUNT(id) as nbr FROM _sugerencias_votes WHERE user='{$request->email}' AND feedback='{$request->query}'");
		if($res[0]->nbr > 0)
		{
			$mensaje = "No puedes votar dos veces por la misma sugerencia. Puedes seleccionar otra de la lista de sugerencias disponibles o escribir una nueva sugerencia.";
			$response->setResponseSubject("No puedes repetir votos.");
			$response->createFromTemplate("fail.tpl", [
				"titulo" => "Ya votastes por esta idea",
				"mensaje" => $mensaje,
				"buttonNew" => true,
				"buttonList" => true
			]);

			return $response;
		}

		// aqui inserto el voto y aumento el contador
		Connection::query("
			INSERT INTO _sugerencias_votes (`user`, feedback) VALUES ('{$request->email}', '{$request->query}');
			UPDATE _sugerencias_list SET votes_count=votes_count+1 WHERE id={$request->query};");

		// check if the idea reached the number of votes to be approved
		if($suggestion->votes_count + 1 >= $suggestion->limit_votes)
		{
			// asign credits to the creator and send a notification
			Connection::query("UPDATE person SET credit=credit+{$this->CREDITS_X_APPROVED} WHERE email='{$suggestion->user}'");
			$msg = "Una sugerencia suya ha sido aprobada y usted gano §{$this->CREDITS_X_APPROVED}. Gracias!";
			$this->utils->addNotification($suggestion->user, "Sugerencias", $msg, "SUGERENCIAS VER {$request->query}");

			// get all the people who voted for the suggestion
			$voters = Connection::query("SELECT `user`, feedback FROM `_sugerencias_votes` WHERE `feedback` = {$request->query}");

			// asign credits to the voters and send a notification
			$longQuery = '';
			foreach($voters as $voter)
			{
				$longQuery .= "UPDATE person SET credit=credit+{$this->CREDITS_X_VOTE} WHERE email='{$voter->user}';";
				$msg       = "Usted voto por una sugerencia que ha sido aprobada y por lo tanto gano §{$this->CREDITS_X_VOTE}";
				$this->utils->addNotification($voter->user, "Sugerencias", $msg, "SUGERENCIAS VER {$voter->feedback}");
			}
			Connection::query($longQuery);

			// mark suggestion as approved
			Connection::query("UPDATE _sugerencias_list SET status='APPROVED', updated=CURRENT_TIMESTAMP WHERE id={$request->query}");
		}

		// create message to send to the user
		$votosDisp --;
		if($votosDisp > 0) $aux = "A&uacute;n le queda(n) $votosDisp voto(s) disponible(s). Si lo desea, puede votar por otra sugerencia de la lista.";
		else $aux = "Ya no tiene ning&uacute;n voto disponible. Ahora debe esperar a que sean aprobadas o descartadas las sugerencias por las cuales vot&oacute; para poder votar por alg&uacute;na otra.";
		$mensaje = "Su voto ha sido registrado satisfactoriamente. $aux";

		// send response object
		$response->setResponseSubject("Voto enviado");
		$response->createFromTemplate("success.tpl", ["titulo" => "Voto enviado", "mensaje" => $mensaje]);

		return $response;

	}

	/**
	 * Read the rules of the game
	 *
	 * @param Request
	 *
	 * @return Response
	 */
	public function _reglas(Request $request)
	{
		$response = new Response();
		$response->setCache();
		$response->setResponseSubject("Como agregar sugerencias y votar");
		$response->createFromTemplate("rules.tpl", []);

		return $response;
	}

	/**
	 * Return all suggestions
	 *
	 * @param \Request $request
	 *
	 * @return \Response
	 */
	public function _todas(Request $request)
	{
		return $this->getMainResponse("Todas las sugerencias recibidas", "No hay sugerencias registradas", $request, - 1);
	}

	/**
	 * Subservice aprobadas
	 *
	 * @param \Request $request
	 *
	 * @return \Response
	 */
	public function _aprobadas(Request $request)
	{
		return $this->getMainResponse("Lista de sugerencias aprobadas", "No hay sugerencias aprobadas", $request, - 1, 'APPROVED', 'updated DESC', 'approved.tpl');
	}

	/**
	 * verify quantity of avaiable votes
	 */
	private function getAvailableVotes($email)
	{
		$res = Connection::query("SELECT COUNT(user) as nbr FROM _sugerencias_votes WHERE user = '$email'");

		return $this->MAX_VOTES_X_USER - $res[0]->nbr;
	}
}
