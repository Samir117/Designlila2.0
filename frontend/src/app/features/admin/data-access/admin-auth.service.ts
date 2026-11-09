import { HttpErrorResponse } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, map, Observable, of, switchMap, tap, throwError } from 'rxjs';
import { ApiClient } from '../../../core/api/api-client.service';

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
}

interface AuthResponse {
  user: AdminUser;
  csrf_token: string;
}

interface CsrfResponse {
  token: string;
}

@Injectable({ providedIn: 'root' })
export class AdminAuthService {
  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);

  readonly user = signal<AdminUser | null>(null);
  private csrfToken: string | null = null;

  login(email: string, password: string): Observable<AdminUser> {
    return this.api.get<CsrfResponse>('auth/csrf').pipe(
      tap(({ token }) => (this.csrfToken = token)),
      switchMap(({ token }) =>
        this.api.post<AuthResponse, { email: string; password: string }>(
          'auth/login',
          { email, password },
          token,
        ),
      ),
      tap(({ user, csrf_token }) => {
        this.user.set(user);
        this.csrfToken = csrf_token;
      }),
      map(({ user }) => user),
    );
  }

  ensureUser(): Observable<boolean> {
    if (this.user()) {
      return of(true);
    }

    return this.api.get<AuthResponse>('auth/me').pipe(
      tap(({ user, csrf_token }) => {
        this.user.set(user);
        this.csrfToken = csrf_token;
      }),
      map(() => true),
      catchError((error: unknown) => {
        if (!(error instanceof HttpErrorResponse) || error.status !== 401) {
          return throwError(() => error);
        }

        this.user.set(null);
        void this.router.navigate(['/admin/login']);
        return of(false);
      }),
    );
  }

  logout(): Observable<void> {
    const ensureToken = this.csrfToken
      ? of(this.csrfToken)
      : this.api.get<CsrfResponse>('auth/csrf').pipe(map(({ token }) => token));

    return ensureToken.pipe(
      switchMap((token) => this.api.post('auth/logout', {}, token)),
      map(() => undefined),
      tap(() => {
        this.user.set(null);
        this.csrfToken = null;
      }),
      catchError((error: unknown) => {
        if (error instanceof HttpErrorResponse && error.status === 401) {
          this.user.set(null);
          this.csrfToken = null;
          return of(undefined);
        }

        return throwError(() => error);
      }),
    );
  }

  token(): string {
    if (!this.csrfToken) {
      throw new Error('Administrative session is not ready.');
    }

    return this.csrfToken;
  }
}
