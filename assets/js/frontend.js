'use strict';

(function($) {
  $(function() {
    wooct_init();
  });

  // WPC Smart Quick View
  $(document).on('woosq_loaded', function() {
    wooct_init();
  });

  // WPC Added To Cart Notification
  $(document).on('wooac_show', function() {
    wooct_init();
  });

  // WPC Fly Cart
  $(document).on('woofc_cart_loaded woofc_show_cart', function() {
    setTimeout(function() {
      wooct_init();
    }, 500);
  });

  // WPC Smart Wishlist
  $(document).on('woosw_wishlist_show', function() {
    wooct_init();
  });

  // WPC Smart Notifications
  $(document).on('wpcsn_build_template', function() {
    wooct_init();
  });

  $(document).on('found_variation', function(e, t) {
    var product_id = $(e['target']).
        closest('.variations_form').
        data('product_id');

    if ((t.wooct != undefined) && (t.wooct !== '')) {
      if ($('.wooct-wrap-single[data-id="' + product_id + '"]').length) {
        $('.wooct-wrap-single[data-id="' + product_id + '"]').hide();

        if ($('.wooct-wrap-single-variation[data-id="' + product_id +
            '"]').length) {
          $('.wooct-wrap-single-variation[data-id="' + product_id + '"]').
              html(wooct_decode_entities(t.wooct)).show();
        } else {
          $('<div class="wooct-wrap-single-variation" data-id="' + product_id +
              '"></div>').
              insertAfter('.wooct-wrap-single[data-id="' + product_id + '"]');
          $('.wooct-wrap-single-variation[data-id="' + product_id + '"]').
              html(wooct_decode_entities(t.wooct)).show();
        }
      } else if ($(
          '.wooct-wrap-variation[data-id="' + product_id + '"]').length) {
        $('.wooct-wrap-variation[data-id="' + product_id + '"]').
            html(wooct_decode_entities(t.wooct)).show();
      }

      wooct_init();
    } else {
      $('.wooct-wrap-single-variation[data-id="' + product_id + '"]').
          html('').
          hide();
      $('.wooct-wrap-variation[data-id="' + product_id + '"]').html('').show();
      $('.wooct-wrap-single[data-id="' + product_id + '"]').show();
    }
  });

  $(document).on('reset_data', function(e) {
    var product_id = $(e['target']).
        closest('.variations_form').
        data('product_id');

    $('.wooct-wrap-single-variation[data-id="' + product_id + '"]').
        html('').
        hide();
    $('.wooct-wrap-variation[data-id="' + product_id + '"]').html('').show();
    $('.wooct-wrap-single[data-id="' + product_id + '"]').show();
  });
})(jQuery);

function wooct_init() {
  jQuery(document).find('.wooct-countdown').each(
      function() {
        var $this = jQuery(this);
        var timer = $this.attr('data-timer');
        var style = $this.attr('data-style');
        var text_ended = $this.attr('data-ended');
        var timer_tz = moment.tz(timer, 'MM/DD/YYYY hh:mm a',
            wooct_vars.timezone);

        if ($this.hasClass('wooct-flipper')) {
          var timestamp = Date.parse(timer_tz.toDate());
          var timer_str = moment(timestamp).format('YYYY-MM-DD HH:mm:ss');

          $this.find('.flipper').
              attr('data-datetime', timer_str).
              flipper('init');

          var interval = setInterval(function() {
            if (Date.now() > timestamp) {
              if (text_ended !== '') {
                $this.removeClass('wooct-running').
                    addClass('wooct-ended').
                    html('<div class="wooct-text-ended">' + text_ended +
                        '</div>');
              } else {
                $this.remove();
              }
              clearInterval(interval);
            }
          }, 1000);
        } else if (!$this.hasClass('wooct-ended')) {
          var timer_format = wooct_vars.timer_format;

          if (wooct_vars.hasOwnProperty('timer_format_' + style)) {
            timer_format = wooct_vars['timer_format_' + style];
          }

          $this.find('.wooct-timer').
              countdown(timer_tz.toDate(), function(event) {
                jQuery(this).html(event.strftime(timer_format));
              }).
              on('finish.countdown', function() {
                if (text_ended !== '') {
                  $this.removeClass('wooct-running').
                      addClass('wooct-ended').
                      html('<div class="wooct-text-ended">' + text_ended +
                          '</div>');
                } else {
                  $this.remove();
                }
              });
        }
      },
  );
}

function wooct_decode_entities(encodedString) {
  var textArea = document.createElement('textarea');
  textArea.innerHTML = encodedString;

  return textArea.value;
}