import { Component, input } from '@angular/core';

@Component({
  selector: 'app-feedback-panel',
  standalone: true,
  template: `
    <section
      class="panel"
      [class.error]="tone() === 'error'"
      [attr.role]="tone() === 'error' ? 'alert' : 'status'"
      aria-live="polite"
    >
      <h2>{{ title() }}</h2>
      @if (message()) {
        <p>{{ message() }}</p>
      }
      <ng-content />
    </section>
  `,
  styleUrl: './feedback-panel.css',
})
export class FeedbackPanel {
  readonly title = input.required<string>();
  readonly message = input('');
  readonly tone = input<'neutral' | 'error'>('neutral');
}
