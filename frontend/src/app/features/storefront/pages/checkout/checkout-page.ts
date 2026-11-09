import { Component } from '@angular/core';
import { FeedbackPanel } from '../../../../shared/ui/feedback-panel/feedback-panel';

@Component({
  selector: 'app-checkout-page',
  standalone: true,
  imports: [FeedbackPanel],
  template: `
    <section aria-labelledby="checkout-title">
      <h1 id="checkout-title">Finalizar compra</h1>
      <app-feedback-panel
        title="Compra como invitada"
        message="El checkout se conectará a la API; el servidor calculará precios y envío."
      />
    </section>
  `,
  styles: [
    `
      h1 {
        margin: 0 0 20px;
        color: var(--color-heading);
        font: 500 clamp(2rem, 5vw, 3.5rem) / 1.1 var(--font-display);
      }
    `,
  ],
})
export class CheckoutPage {}
