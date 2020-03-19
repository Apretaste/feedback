$(function(){

    $('.tabs').tabs();

    $('.fixed-action-btn').floatingActionButton({
        direction: 'top',
        hoverEnabled: false
    });

});

function formatDate(dateStr) {
    return moment(dateStr).format('DD/MM/YYYY');
}

function formatDateTime(dateStr) {
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