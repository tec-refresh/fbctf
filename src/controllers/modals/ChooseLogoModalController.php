<?php declare(strict_types=1);

class ChooseLogoModalController extends ModalController {
  public function render(string $_): string {
    return
      '<div class="fb-modal-content">' . '<header class="modal-title">' . '<h4>' . htmlspecialchars(tr('choose_logo')) . '</h4>' . '<a href="#" class="js-close-modal">' . '<svg class="icon icon--close">' . '<use href="#icon--close" />' . '</svg>' . '</a>' . '</header>' . '<div class="choose-logo-modal">' . '<div class="fb-choose-emblem">' . '<h6>' . htmlspecialchars(tr('Choose an Emblem')) . '</h6>' . '<div class="emblem-carousel">' . '<emblem-carousel />' . '</div>' . '</div>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--yellow js-close-modal js-store-logo">' . htmlspecialchars(tr('Save')) . '</a>' . '</div>' . '</div>' . '</div>';
  }
}
