import {
  HttpClient,
  HttpErrorResponse,
  HttpHeaders,
  HttpParams,
} from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { catchError, map, Observable, throwError } from 'rxjs';
import { API_BASE_URL } from './api-base-url';
import { ApiEnvelope } from './api-envelope';

@Injectable({ providedIn: 'root' })
export class ApiClient {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = inject(API_BASE_URL).replace(/\/+$/, '');

  get<T>(path: string, params?: HttpParams): Observable<T> {
    return this.http
      .get<ApiEnvelope<T>>(this.url(path), { params, credentials: 'same-origin' })
      .pipe(
        map((response) => this.unwrap(response)),
        catchError((error: unknown) => this.reportError('GET', path, error)),
      );
  }

  post<TResponse, TBody>(
    path: string,
    body: TBody,
    csrfToken?: string,
  ): Observable<TResponse> {
    return this.http
      .post<ApiEnvelope<TResponse>>(this.url(path), body, {
        credentials: 'same-origin',
        headers: this.headers(csrfToken),
      })
      .pipe(
        map((response) => this.unwrap(response)),
        catchError((error: unknown) => this.reportError('POST', path, error)),
      );
  }

  put<TResponse, TBody>(
    path: string,
    body: TBody,
    csrfToken: string,
  ): Observable<TResponse> {
    return this.http
      .put<ApiEnvelope<TResponse>>(this.url(path), body, {
        credentials: 'same-origin',
        headers: this.headers(csrfToken),
      })
      .pipe(
        map((response) => this.unwrap(response)),
        catchError((error: unknown) => this.reportError('PUT', path, error)),
      );
  }

  delete<TResponse>(path: string, csrfToken: string): Observable<TResponse> {
    return this.http
      .delete<ApiEnvelope<TResponse>>(this.url(path), {
        credentials: 'same-origin',
        headers: this.headers(csrfToken),
      })
      .pipe(
        map((response) => this.unwrap(response)),
        catchError((error: unknown) => this.reportError('DELETE', path, error)),
      );
  }

  private url(path: string): string {
    return `${this.baseUrl}/${path.replace(/^\/+/, '')}`;
  }

  private headers(csrfToken?: string): HttpHeaders {
    return csrfToken
      ? new HttpHeaders({ 'X-CSRF-Token': csrfToken })
      : new HttpHeaders();
  }

  private unwrap<T>(response: ApiEnvelope<T>): T {
    if (!response.success) {
      throw new Error(response.error.message);
    }

    return response.data;
  }

  private reportError(
    method: string,
    path: string,
    error: unknown,
  ): Observable<never> {
    const endpoint = path.split('?', 1)[0];
    const detail =
      error instanceof HttpErrorResponse
        ? `HTTP ${error.status || 'network'}`
        : error instanceof Error
          ? error.message
          : 'Unknown error';

    console.error(`[API] ${method} ${endpoint} failed: ${detail}`);
    return throwError(() => error);
  }
}
