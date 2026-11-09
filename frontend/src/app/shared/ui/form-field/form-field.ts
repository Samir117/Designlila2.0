import { Component, input, ViewEncapsulation } from '@angular/core';

@Component({
  selector: 'app-form-field',
  standalone: true,
  template: `
    <div class="dl-form-field" [class.dl-form-field--invalid]="error()">
      <label class="dl-form-field__label">
        <span class="dl-form-field__label-text">
          {{ label() }}
          @if (required()) {
            <span class="dl-form-field__required" aria-hidden="true">*</span>
          }
        </span>
        <ng-content select="input, select, textarea" />
      </label>
      @if (hint()) {
        <span class="dl-form-field__message">{{ hint() }}</span>
      }
      @if (error()) {
        <span class="dl-form-field__message dl-form-field__message--error" role="alert">
          {{ error() }}
        </span>
      }
    </div>
  `,
  encapsulation: ViewEncapsulation.None,
  styleUrl: './form-field.css',
})
export class FormField {
  readonly label = input.required<string>();
  readonly hint = input('');
  readonly error = input('');
  readonly required = input(false);
}
