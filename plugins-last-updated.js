jQuery(document).ready(function($) {

    $('.plugin-last-updated-humanreadable').each(function() {

        var color = $(this).data('color');

        if( color && color.length > 0 ) {

            $(this).parent().css('background-color', color)

        }

    })

    // Move the "Clear Update Cache" button next to the item count above the plugins table.
    // Without JavaScript it stays where PHP printed it, just above the table.
    var $button = $('.sk-plu-clear-cache-wrap .sk-plu-clear-cache');
    var $count  = $('.tablenav.top .displaying-num').first();

    if( $button.length && $count.length ) {

        $count.prepend($button);
        $('.sk-plu-clear-cache-wrap').remove();

    }

})
