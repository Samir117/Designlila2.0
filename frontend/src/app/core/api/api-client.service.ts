import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-envelope';

@Injectable({ providedIn: 'root' })
export class ApiClient {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiBaseUrl.replace(/\/+$/, '');

  get<T>(path: string, params?: HttpParams): Observable<T> {
    return this.http
      .get<ApiEnvelope<T>>(this.url(path), { params, credentials: 'same-origin' })
      .pipe(map((response) => this.unwrap(response)));
  }

  post<TResponse, TBody>(path: string, body: TBody): Observable<TResponse> {
    return this.http
      .post<ApiEnvelope<TResponse>>(this.url(path), body, {
        credentials: 'same-origin',
      })
      .pipe(map((response) => this.unwrap(response)));
  }

  private url(path: string): string {
    return `${this.baseUrl}/${path.replace(/^\/+/, '')}`;
  }

  private unwrap<T>(response: ApiEnvelope<T>): T {
    if (!response.success) {
      throw new Error(response.error.message);
    }

    return response.data;
  }
}
