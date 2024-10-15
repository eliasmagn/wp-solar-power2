jQuery(document).ready(function($) {
    // Handle adding new entities
    $('#solarpower-add-entity').on('click', function(e) {
        e.preventDefault();
        var index = $('#solarpower-entities-table tr').length;
        var newRow = `
        <tr>
            <td><input type="checkbox" name="solarpower_options[entities][entity_${index}][enabled]" value="1"></td>
            <td><input type="text" name="solarpower_options[entities][entity_${index}][entity_id]" value=""></td>
            <td><input type="text" name="solarpower_options[entities][entity_${index}][label]" value=""></td>
            <td><input type="text" name="solarpower_options[entities][entity_${index}][unit]" value=""></td>
            <td>
                <select name="solarpower_options[entities][entity_${index}][aggregation]">
                    <option value="average"><?php _e('Average', 'solarpower-data'); ?></option>
                    <option value="sum"><?php _e('Sum', 'solarpower-data'); ?></option>
                </select>
            </td>
            <td><button class="button solarpower-remove-entity"><?php _e('Remove', 'solarpower-data'); ?></button></td>
        </tr>`;
        $('#solarpower-entities-table').append(newRow);
    });

    // Handle removing entities
    $('#solarpower-entities-table').on('click', '.solarpower-remove-entity', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    // Toggle external database fields
    function toggleExternalDbFields() {
        if ($('#use_external_db').is(':checked')) {
            $('input[name^="solarpower_options[external_db_"]').closest('tr').show();
        } else {
            $('input[name^="solarpower_options[external_db_"]').closest('tr').hide();
        }
    }

    toggleExternalDbFields();

    $('#use_external_db').change(function() {
        toggleExternalDbFields();
    });
});
