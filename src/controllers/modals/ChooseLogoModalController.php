<?php declare(strict_types=1);

class ChooseLogoModalController extends ModalController {
  public function render(string $_): string {
    $all_logos = Logo::allEnabledLogos();

    $logo_items = '';
    foreach ($all_logos as $logo) {
      $name = htmlspecialchars($logo->getName());
      $logo_items .=
        '<li>' .
          '<svg class="icon--badge">' .
            '<use xlink:href="#icon--badge-' . $name . '" />' .
          '</svg>' .
        '</li>';
    }

    return
      '<div class="fb-modal-content">' .
        '<header class="modal-title">' .
          '<h4>' . htmlspecialchars(tr('choose_logo')) . '</h4>' .
          '<a href="#" class="js-close-modal">' .
            '<svg class="icon icon--close">' .
              '<use href="#icon--close" />' .
            '</svg>' .
          '</a>' .
        '</header>' .
        '<div class="choose-logo-modal">' .
          '<div class="fb-choose-emblem">' .
            '<h6>' . htmlspecialchars(tr('Choose an Emblem')) . '</h6>' .
            '<div class="emblem-carousel">' .
              '<ul class="slides">' . $logo_items . '</ul>' .
            '</div>' .
          '</div>' .
          '<div class="action-actionable">' .
            '<a href="#" class="fb-cta cta--yellow js-close-modal js-store-logo">' .
              htmlspecialchars(tr('Save')) .
            '</a>' .
          '</div>' .
        '</div>' .
      '</div>';
  }
}
