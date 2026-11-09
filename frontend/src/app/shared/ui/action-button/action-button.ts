import { Directive, input } from '@angular/core';

@Directive({
  selector: 'button[appActionButton]',
  standalone: true,
  host: {
    class: 'dl-button',
    '[class.dl-button--primary]': "variant() === 'primary'",
    '[class.dl-button--outline]': "variant() === 'outline'",
    '[class.dl-button--soft]': "variant() === 'soft'",
    '[attr.aria-busy]': "busy() ? 'true' : null",
  },
})
export class ActionButton {
  readonly variant = input<'primary' | 'outline' | 'soft'>('primary');
  readonly busy = input(false);
}
