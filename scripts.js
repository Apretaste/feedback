$(function(){
	$('.tabs').tabs();
	$('.modal').modal();
	$('select').formSelect();
});

function sendMessage() {
	var message = $('#message').val().trim();

	if (message.length < 20 || message.length > 300) {
		M.toast({html: "Escriba entre 20 y 300 caracteres"});
		return false;
	}

	apretaste.send({
		'command': 'SUGERENCIAS CREAR',
		'data': {'message': message}
	});
}

function startSearch() {
	var username = $('#username').val().trim();
	var status = $('#status').val();
	var text = $('#text').val();

	apretaste.send({
		'command': 'SUGERENCIAS ENCONTRAR',
		'data': {
			'username': username, 
			'status': status,
			'text': text
		}
	});
}
