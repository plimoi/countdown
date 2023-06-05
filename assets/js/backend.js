'use strict';

(function($) {
  $(function() {
    wooct_time_picker();
    wooct_time_type();
  });

  $('#woocommerce-product-data').
      on('woocommerce_variations_loaded woocommerce_variations_added',
          function() {
            wooct_time_picker();
            wooct_time_type();
          });

  $(document).
      on('change blur',
          '.wooct_shortcode_builder input, .wooct_shortcode_builder select',
          function() {
            wooct_build_shortcode();
          });

  $(document).on('change', '.wooct_time', function() {
    wooct_time_type();
  });

  function wooct_time_picker() {
    $('.wooct_date_time').wpcdpk({
      timepicker: true,
    });

    $('.wooct_date').wpcdpk();

    $('.wooct_date_range').wpcdpk({
      range: true,
      multipleDatesSeparator: ' - ',
    });

    $('.wooct_date_multi').wpcdpk({
      multipleDates: 5,
      multipleDatesSeparator: ', ',
    });

    $('.wooct_time').wpcdpk({
      dateFormat: ' ',
      timepicker: true,
      classes: 'only-time',
    });
  }

  function wooct_time_type() {
    if ($('.wooct_time').length) {
      $('.wooct_time').each(function() {
        if ($(this).val() == 'custom') {
          $(this).closest('table').find('.wooct_show_if_custom').show();
        } else {
          $(this).closest('table').find('.wooct_show_if_custom').hide();
        }
      });
    }
  }

  function wooct_build_shortcode() {
    var wooct_time_start = $('.wooct_time_start').val();
    var wooct_time_end = $('.wooct_time_end').val();
    var wooct_text_above = $('.wooct_text_above').val();
    var wooct_text_under = $('.wooct_text_under').val();
    var wooct_text_ended = $('.wooct_text_ended').val();
    var wooct_style = $('.wooct_style').val();

    var wooct_shortcode = '[wooct style="' +
        wooct_style + '" time_start="' + wooct_time_start +
        '" time_end="' +
        wooct_time_end + '" text_above="' +
        wooct_html_entities(wooct_text_above) + '" text_under="' +
        wooct_html_entities(wooct_text_under) + '" text_ended="' +
        wooct_html_entities(wooct_text_ended) + '"]';

    $('.wooct_shortcode').val(wooct_shortcode);
  }

  function wooct_html_entities(str) {
    return String(str).
        replace(/&/g, '&amp;').
        replace(/</g, '&lt;').
        replace(/>/g, '&gt;').
        replace(/"/g, '&quot;');
  }
})(jQuery);