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

function teaser(text) {
	return text.length <= 50 ? text : text.substr(0, 50) + "...";
}

var share;

function init(suggest) {
	share = {
		text: teaser(suggest.text),
		icon: 'lightbulb',
		send: function () {
			apretaste.send({
				command: 'PIZARRA PUBLICAR',
				redirect: false,
				callback: {
					name: 'toast',
					data: 'La sugerencia fue compartida en Pizarra'
				},
				data: {
					text: $('#message').val(),
					image: '',
					link: {
						command: btoa(JSON.stringify({
							command: 'SUGERENCIAS VER',
							data: {
								id: item.id
							}
						})),
						icon: share.icon,
						text: share.text
					}
				}
			})
		}
	};
}

function toast(message){
	M.toast({html: message});
}

function removeTags(str) {
	if ((str===null) || (str===''))
		return '';
	else
		str = str.toString();

	return str.replace( /(<([^>]+)>)/ig, '');
}