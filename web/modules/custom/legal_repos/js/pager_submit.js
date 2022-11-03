(function (Drupal, document) {

  Drupal.behaviors.pager_submit = {
    attach: function (context, settings) {

      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      // Submit the search form automatically when the pager next or prev radios
      // are clicked.
      let pager_links = context.querySelectorAll('input[name="page"]');

      pager_links.forEach((pager_link) => {
        pager_link.classList.add('has-js');
        pager_link.addEventListener('click', function (event) {
          event.target.closest('form').submit();
        });
      });

    }
  }

} (Drupal, document));
