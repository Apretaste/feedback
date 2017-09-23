<?php

class Sugerencias extends Service{
	private $CREDITS_X_APPROVED = 5;
	private $CREDITS_X_VOTE = 0.5;
	private $MAX_VOTES_X_USER = 5;
	private $MAX_FEEDBACKS_X_USER = 3;

	/**
	 * Function executed when the service is called
	 *
	 * @param Request
	 * @return Response

	 */
	public function _main(Request $request){
		$email = $request->email;
		$userName = $request->name;

		$response = new Response();
		if (empty($request->query)){
			// get list of tickets
			$connection = new Connection();
			$result = $connection->deepQuery("SELECT id, user, subject, body, likes_count, limit_votes, creation_date, limit_date FROM feedback_tickets WHERE status = 'NEW' ORDER BY creation_date DESC");

			$tickets = array();

			// create array of arrays
			foreach($result as $ticket){
				array_push($tickets, $ticket);
			}

			// get variables to send to the template
			if (count($tickets) > 0)
			{
				$votosDisp = $this->getAvaiableVotes($request->email);
				if($votosDisp != 0){
					$voteButtonEnabled = true;
				}else{
					$voteButtonEnabled = false;
				}

				$responseContent = array(
					"tickets" => $tickets,
					"userName" => $userName,
					"userEmail" => $email,
					"ticketsNum" => count($tickets),
					"votosDisp" => $votosDisp,
					"voteButtonEnabled" => $voteButtonEnabled
				);

				//$response->setCache(180);
				$response->setResponseSubject("Lista de sugerencias recibidas");
				$response->createFromTemplate("ticketsList.tpl", $responseContent);
			}
			else{
				$mensaje = "Actualmente no hay registrada ninguna sugerencia. Puedes a&ntilde;adir una usando el boton de abajo. ";
				$response->setResponseSubject("No hay ninguna sugerencia abierta todavia.");
				$response->createFromTemplate("noSuccess.tpl", array("titulo"=>"No hay sugerencias registradas", "mensaje" => $mensaje, "buttonNew" => true, "buttonList" => false));
			}
		}else //si el request viene con un parametro
		{
			if (is_numeric($request->query)){
				$mensaje = "Esta sugerencia no se entiende. Por favor escribe una idea v&aacute;lida, puedes a&ntilde;adir una usando el boton de abajo. ";
				$response->setResponseSubject("Sugerencia no valida.");
				$response->createFromTemplate("noSuccess.tpl", array("titulo"=>"Sugerencia no v&aacute;lida.", "mensaje" => $mensaje, "buttonNew" => true, "buttonList" => false));
			}else{
				$response = $this->createFeedback($request);
			}
		}

		$this->checkApprovedFedbacks();
		// return
		return $response;
	}

	/**
	 * Create a new FeedBack
	 */
	private function createFeedback(Request $request){
		// insert ticket and delete tickets out of limit
		$connection = new Connection();
		$avaiableFeedbacks = $this->getAvaiableFeedbacks($request->email);
		$response = new Response();

		if($avaiableFeedbacks != 0){	
			$fecha = new DateTime();
			$fechaNow = new DateTime();
			$fechaLimite = $fecha->modify('+15 days');
			$limitVotes = $this->getLimitVotes();

			$connection->deepQuery("
				INSERT INTO `feedback_tickets` (`user`, `subject`, `body`, `limit_votes`, `limit_date`) 
				VALUES ('{$request->email}', 'FeedBack from {$request->email}', '{$request->query}', {$limitVotes},'{$fechaLimite->format('Y-m-d H:i:s')}'); DELETE FROM `feedback_tickets` WHERE limit_date <= '{$fechaNow->format('Y-m-d H:i:s')}' AND likes_count < {$limitVotes};");

			// create response
			$mensaje = "Su sugerencia ha sido registrada satisfactoriamente. Ya est&aacute; visible en la lista de sugerencias para que todos puedan votar por ella. Cada usuario(incluido t&uacute;) podr&aacute; votar por ella s&oacute;lo una vez, y si llega a sumar {$limitVotes} votos o m&aacute;s en un plazo de 15 d&iacute;as, ser&aacute; aprobada, si no, se descartar&aacute; y t&uacute; podr&aacute;s enviar otra sugerencia.";
			$response->setResponseSubject("Sugerencia enviada");
			$response->createFromTemplate("success.tpl", array("titulo"=>"Sugerencia enviada", "mensaje"=>$mensaje));
		}else{
			$mensaje = "Solo puedes incluir {$this->$this->MAX_FEEDBACKS_X_USER} sugerencias cada vez. Debes esperar a que tus sugerencias sean aprobadas o que pasen 15 d&iacute;as para poder incluir otra idea. Mientras tanto, puedes ver la lista de sugerencias disponibles.";
			$response->setResponseSubject("No puedes incluir otra sugerencia por ahora.");
			$response->createFromTemplate("noSuccess.tpl", array("titulo"=>"No puedes incluir otra sugerencia por ahora.", "mensaje" => $mensaje, "buttonNew" => false, "buttonList" => true));
		}
		return $response;
	}

/**
	 * Sub-service ver, Display a full ticket
	 * @param Request
	 * @return Response
	 */
	public function _ver(Request $request){
		if (!empty($request->query)){
			// get ticket
			$connection = new Connection();
			$result = $connection->deepQuery("SELECT * FROM feedback_tickets WHERE id = '$request->query';");

			$response = new Response();
			if ($result){
				$ticket = $result[0];
				//$ticket->username = $this->utils->getUsernameFromEmail($ticket->user);
				$ticket->username = $ticket->user;
				$votosDisp = $this->getAvaiableVotes($request->email);
				if($votosDisp != 0){
					$voteButtonEnabled = true;
				}else{
					$voteButtonEnabled = false;
				}

				$response->setResponseSubject("Sugerencia #{$ticket->id}");
				$response->createFromTemplate("showTicket.tpl", array("ticket" => $ticket, "voteButtonEnabled" => $voteButtonEnabled));
			}else{
				$mensaje = "Disculpe, el ticket solicitado no se encuentra registrado. Por favor verifique el numero e intente de nuevo.";
				$response->setResponseSubject("Sugerencia no encontrada.");
				$response->createFromTemplate("noSuccess.tpl", array("titulo"=>"Sugerencia no encontrada.", "mensaje" => $mensaje, "buttonNew" => false, "buttonList" => true));
			}
		}else{
			$mensaje = "No has seleccionado ning&uacute;na sugerencia para ver. Debes seleccionar una opci&oacute;n v&aacute;lida, puedes ver la lista de sugerencias disponibles para elegir una.";
			$response->setResponseSubject("¿Cual idea deseas ver?");
			$response->createFromTemplate("noSuccess.tpl", array("titulo"=>"¿Cual idea deseas ver?", "mensaje" => $mensaje, "buttonNew" => false, "buttonList" => true));
		}
		//$response->setCache();
		return $response;	
	}

	/**
	 * Sub-service votar
	 * @param Request
	 * @return Response
	 */
	public function _votar(Request $request){
		$response = new Response();
		if (!empty($request->query)){
			$votosDisp = $this->getAvaiableVotes($request->email);
			if ($votosDisp!=0){
				$connection = new Connection();
				$uniqueVote = $this->uniqueVote($request->email,$request->query);
				if ($uniqueVote){
					$fechaNow = new DateTime();
					//aqui inserto el voto, aumento el contador y aprovecho para borrar los tickets fuera de limite
					$connection->deepQuery("
					INSERT INTO `feedback_votes` (`user`, `feedback`) VALUES ('{$request->email}', '{$request->query}');
					UPDATE `feedback_tickets` SET likes_count = likes_count + 1 WHERE id = '{$request->query}';");

					$votosDisp = $this->getAvaiableVotes($request->email); //cuantos votos quedan despues de votar
					if ($votosDisp > 0){
						$aux ="A&uacute;n te queda(n) {$votosDisp} voto(s) disponible(s). Si lo deseas, puedes votar por otra sugerencia de la lista.";
					}else{
						$aux ="Ya no tienes ning&uacute;n voto disponible. Ahora debes esperar a que sean aprobadas o descartadas las sugerencias por las que votaste para poder votar por alg&uacute;na otra. Mientras tanto, puedes ver la lista de sugerencias disponibles.";
					}

					$mensaje = "Su voto ha sido registrado satisfactoriamente. ".$aux;
					$response->setResponseSubject("Voto enviado");
					$response->createFromTemplate("success.tpl", array("titulo"=>"Voto enviado", "mensaje" => $mensaje));
				}else{
					$mensaje = "Ya votaste por esta idea. No puedes votar dos veces por la misma sugerencia. Puedes seleccionar otra de la lista de sugerencias disponibles o escribir una nueva sugerencia.";
					$response->setResponseSubject("No puedes repetir votos.");
					$response->createFromTemplate("noSuccess.tpl", array("titulo"=>"No puedes repetir votos.", "mensaje" => $mensaje, "buttonNew" => true, "buttonList" => true));
				}
			}else{
				$mensaje = "No tienes ning&uacute;n voto disponible. Debes esperar a que sean aprobadas o descartadas las sugerencias por las que votaste para poder votar por alg&uacute;na otra. Mientras tanto, puedes ver la lista de sugerencias disponibles o escribir una nueva sugerencia.";
				$response->setResponseSubject("No puedes votar por ahora.");
				$response->createFromTemplate("noSuccess.tpl", array("titulo"=>"No puedes votar por ahora.", "mensaje" => $mensaje, "buttonNew" => true, "buttonList" => true));
			}
		}else{
			$mensaje = "No has seleccionado ning&uacute;na sugerencia para votar. Debes votar por una opci&oacute;n v&aacute;lida, puedes ver la lista de sugerencias disponibles o escribir una nueva si as&iacute; lo deseas.";
			$response->setResponseSubject("¿Por cual idea deseas votar?");
			$response->createFromTemplate("noSuccess.tpl", array("titulo"=>"¿Por cual idea deseas votar?", "mensaje" => $mensaje, "buttonNew" => true, "buttonList" => true));
		}

		return $response;
	}

	/**
	 * verify quantity of avaiable votes
	 */
	private function getAvaiableVotes($email){
		$avaiableVotes = $this->MAX_VOTES_X_USER;
		$connection = new Connection();
		$result = $connection->deepQuery("SELECT user FROM feedback_votes WHERE user = '{$email}';");
		foreach ($result as $value) {
			$avaiableVotes = $avaiableVotes - 1;
			if ($avaiableVotes == 0) break;
		}
		return $avaiableVotes;
	}

	/**
	 * verify only one vote per feedback
	 */
	private function uniqueVote($email, $feedback){
		$uniqueVote = false;
		$connection = new Connection();
		$result = $connection->deepQuery("SELECT user FROM feedback_votes WHERE user = '{$email}' AND feedback = '{$feedback}';");
		if (!$result){
			$uniqueVote = true;
		}
		return $uniqueVote;
	}

	/**
	 * verify avaiable feedbacks per user
	 */
	private function getAvaiableFeedbacks($email){
		$avaiableFeedbacks = $this->MAX_FEEDBACKS_X_USER;
		$connection = new Connection();
		$result = $connection->deepQuery("SELECT user FROM feedback_tickets WHERE user = '{$email}' AND status = 'NEW';");
		foreach ($result as $value) {
			$avaiableFeedbacks = $avaiableFeedbacks - 1;
			if ($avaiableFeedbacks == 0) break;
		}
		return $avaiableFeedbacks;
	}

	/**
	 * verify for feedbacks approved and set credits
	 */
	private function checkApprovedFedbacks(){
		$connection = new Connection();
		$result = $connection->deepQuery("SELECT id, user 
			FROM feedback_tickets 
			WHERE likes_count >= limit_votes AND status != 'APPROVED' AND payment_date IS NULL;
		");

		foreach ($result as $feedback) {
			// add credit to the user account
			$connection->deepQuery("UPDATE person SET credit=credit+{$this->CREDITS_X_APPROVED} WHERE email='{$feedback->user}'");
			$connection->deepQuery("UPDATE feedback_tickets SET status = 'APPROVED', payment_date = NOW(), 	payment_amount = {$this->CREDITS_X_APPROVED} WHERE user = '{$feedback->user}' AND id = {$feedback->id};");

			//ahora a los usuarios que votaron por ese feedback
			$result2 = $connection->deepQuery("SELECT * FROM feedback_votes WHERE feedback = $feedback->id;");
			foreach ($result2 as $voto) {
				$connection->deepQuery("UPDATE person SET credit=credit+{$this->CREDITS_X_VOTE} WHERE email='{$voto->user}';");
			}
		}

		return 1;
	}

	/**
	 * get limit votes
	 */
	private function getLimitVotes(){
		$limit_votes = 0.0;
		$connection = new Connection();

		$result = $connection->deepQuery("SELECT COUNT(email) AS total FROM person WHERE active = 1;");
		$numberUsersActive = $result[0]->total;
		$limit_votes = ($numberUsersActive * 0.01); //1% of users active

		return ceil($limit_votes);
	}	
}

/*
--
-- Table structure for table `feedback_tickets`
--

DROP TABLE IF EXISTS `feedback_tickets`;
CREATE TABLE IF NOT EXISTS `feedback_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` char(100) NOT NULL,
  `subject` varchar(250) NOT NULL,
  `body` varchar(1024) NOT NULL,
  `likes_count` int(11) NOT NULL DEFAULT 0,
  `limit_votes` int(11) NOT NULL DEFAULT 0,
  `status` enum('NEW','APPROVED','DISCARDED') NOT NULL DEFAULT 'NEW',
  `creation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `limit_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `payment_date` timestamp NULL DEFAULT NULL COMMENT 'Date of credit assignment when approved',
  `payment_amount` float DEFAULT NULL COMMENT 'Credits assigned when approved',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0 ;

--
-- Dumping data for table `feedback_tickets`
--

INSERT INTO `feedback_tickets` (`id`, `user`, `subject`, `body`, `limit_date`) VALUES
(1, 'html@apretaste.com', 'feedback from html@apretaste.com', 'hagan un servicio de ....', '2017-10-01 20:44:57');

//-----------------------------------------------------------------------------------------------------------------------

DROP TABLE IF EXISTS `feedback_votes`;
CREATE TABLE IF NOT EXISTS `feedback_votes` (
  `user` char(100) NOT NULL,
  `feedback` int(11) NOT NULL,
  `vote_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vote_date`),
    FOREIGN KEY (`feedback`)
        REFERENCES `feedback_tickets` (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

*/