$(function(){

    moment.locale("es");

    $('.tabs').tabs();

    $('.fixed-action-btn').floatingActionButton({
        direction: 'top',
        hoverEnabled: false
    });

});

function formatDate(dateStr) {
    moment.locale("es");
    return moment(dateStr).format('DD/MM/YYYY');
}

function formatDateTime(dateStr) {
    moment.locale("es");
    return moment(dateStr).format('D [de] MMMM [del] YYYY [a las] h:mm A');
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
    } else {
        $('#writeModal').slideToggle({
            direction: "up"
        }).attr('status', 'closed');
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
        M.toast({html: "Escriba un poco mas"});
    }
}

function cmpTabs(active) {
    return '<div class = "row">\n' +
        '<div class = "col s12">\n' +
        '<ul class = "tabs tabs-fixed-width">\n' +
        '<li class = "tab"><a href = "#" ' + (active == 1 ? 'class="active"' : 'onclick = "apretaste.send({command: \'SUGERENCIAS\'})"') + '>Abiertas</a></li>\n' +
        '<li class = "tab"><a href = "#" ' + (active == 2 ? 'class="active"' : 'onclick = "apretaste.send({command: \'SUGERENCIAS APROBADAS\'})"') + '>Aprobadas</a></li>\n' +
        '<li class = "tab"><a href = "#" ' + (active == 3 ? 'class="active"' : 'onclick = "apretaste.send({command: \'SUGERENCIAS REGLAS\'})"') + '>Reglas</a></li>\n' +
        '</ul>\n' +
        '</div>\n' +
        '</div>'
}
