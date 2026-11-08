import { Component, input } from '@angular/core';

@Component({
  selector: 'app-action-button',
  standalone: true,
  template: `
    <button
      [attr.type]="type()"
      [class.secondary]="variant() === 'secondary'"
      [disabled]="disabled()"
      [attr.aria-busy]="busy()"
    >
      <ng-content />
    </button>
  `,
  styleUrl: './action-button.css',
})
export class ActionButton {
  readonly type = input<'button' | 'submit' | 'reset'>('button');
  readonly variant = input<'primary' | 'secondary'>('primary');
  readonly disabled = input(false);
  readonly busy = input(false);
}
