import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [RouterLink],
  template: `
    <section class="hero" aria-labelledby="home-title">
      <div class="hero-copy">
        <p class="eyebrow">Papelería creativa · Regalos con intención</p>
        <h1 id="home-title">Un detalle pequeño.<br />Un recuerdo para siempre.</h1>
        <p class="intro">
          Creamos piezas personalizadas para acompañar tus celebraciones,
          desde la primera idea hasta el último moño.
        </p>
        <a class="button" routerLink="/" fragment="catalogo">Explorar la tienda</a>
      </div>
      <div class="hero-art" role="img" aria-label="Composición de papelería y regalos en tonos lila">
        <span class="orbit orbit-one"></span>
        <span class="orbit orbit-two"></span>
        <span class="gift-card">hecho<br />para ti</span>
        <span class="ribbon ribbon-one"></span>
        <span class="ribbon ribbon-two"></span>
      </div>
    </section>

    <section class="catalog-intro" id="catalogo" aria-labelledby="catalog-title">
      <p class="eyebrow">Diseñado para tus momentos</p>
      <h2 id="catalog-title">Detalles que cuentan tu historia</h2>
      <p>
        Esta base está lista para conectar el catálogo real y sus categorías
        cuando iniciemos la integración con la API.
      </p>
    </section>
  `,
  styleUrl: './home-page.css',
})
export class HomePage {}
