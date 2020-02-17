function showToast(text) {
  M.toast({
    html: text
  });
}
$(function(){

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

function post(){
  var text = $('#text').value();

  if (text.length < 10) {
    showToast("Escriba un poco mas");
    return;
  }
  apretaste.send({
    command: 'SUGERENCIAS CREAR',
    query: text
  });
}