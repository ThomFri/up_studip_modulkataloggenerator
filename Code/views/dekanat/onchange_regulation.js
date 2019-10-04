

function onChangeRegulation() {

    $.ajax({
        type: 'GET',
        dataType: 'json',
        url: STUDIP.ABSOLUTE_URI_STUDIP+'plugins.php/modulkatalog_plugin_gruppe2/dekanat/populateRegulation',
        data: {id: $('#major-drop').val()},
        success: function (data) {
            $('#reg-drop').empty();
            for (var i = 0; i < data.length; i++) {
                $('#reg-drop').append($('<option>').attr('value', data[i]).html(data[i]));
            }
        }
        });

}
