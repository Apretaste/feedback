function pad(n, width, z) {
  z = z || '0';
  n = n + '';
  return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
}

function showToast(text) {
  M.toast({
    html: text
  });
}
$(function(){

  $('.tabs').tabs();

  $('.fixed-action-btn').floatingActionButton({
    direction: 'top',
    hoverEnabled: false
  });

});

function formatDate(dateStr) {
  var date = new Date(dateStr);
  var year = date.getFullYear();
  var month = (1 + date.getMonth()).toString().padStart(2, '0');
  var day = date.getDate().toString().padStart(2, '0');
  return day + '/' + month + '/' + year;
}

function formatDateTime(dateStr) {
  var months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var date = new Date(dateStr);
  var month = date.getMonth();
  var day = pad(date.getDay(),2);
  var hour = (date.getHours() < 12) ? date.getHours() : date.getHours() - 12;
  var minutes = date.getMinutes();
  if (minutes < 10) {
    minutes = '0' + minutes;
  }
  var amOrPm = (date.getHours() < 12) ? "am" : "pm";
  return day + ' de ' + months[month] + ' a las ' + hour + ':' + minutes + amOrPm;
}

function toggleWriteModal() {
    var status = $('#writeModal').attr('status');

    if (status == "closed") {
        if ($('.container:not(#writeModal) > .row').length == 3) {
            var h = $('.container:not(#writeModal) > .row')[0].clientHeight;
            $('#writeModal').css('height', 'calc(100% - ' + h + 'px)');
        }

        $('#writeModal').slideToggle({
            direction: "up"
        }).attr('status', 'opened');
        $('#note').focus();

        $("#createButton").hide();
    } else {
        $('#writeModal').slideToggle({
            direction: "up"
        }).attr('status', 'closed');
        $("#createButton").show();
    }
}

function sendNote() {
    var note = $('#note').val().trim();

    if (note.length >= 20) {
        apretaste.send({
            'command': 'SUGERENCIAS CREAR',
            'data': {
                'query': note
            }
        });
    } else {
        showToast('Minimo 10 caracteres');
    }
}