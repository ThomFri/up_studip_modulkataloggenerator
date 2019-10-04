

function onChangeProfs() {
    $.ajax({
        type: 'GET',
        dataType: 'json',
        url: STUDIP.ABSOLUTE_URI_STUDIP+'plugins.php/modulkatalog_plugin_gruppe2/dekanat/populateProfs',
        data: {id: $('#lehrstuhl-drop').val()},
        success: function (data) {
            $('#prof-drop').empty();
            for (var i = 0; i < data.length; i++) {
                if(data[i].username!=null&&data[i].username!=="unipassau_nn")
                    $('#prof-drop').append($('<option>').attr('value', data[i].username).html(data[i].vorname + " " + data[i].nachname));
            }
        }
        });
}
