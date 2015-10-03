jQuery(document).ready(function($) {

    $('.plugin-last-updated-humanreadable').each(function() {

        var color = $(this).data('color');

        if( color.length > 0 ) {

            $(this).parent().css('background-color', color)

        }

    })

})