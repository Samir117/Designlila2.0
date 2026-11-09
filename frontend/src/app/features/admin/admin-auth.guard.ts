import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { AdminAuthService } from './data-access/admin-auth.service';

export const adminAuthGuard: CanActivateFn = () => {
  const auth = inject(AdminAuthService);
  const router = inject(Router);

  return auth.ensureUser().pipe(
    map((authenticated) =>
      authenticated ? true : router.createUrlTree(['/admin/login']),
    ),
    catchError((error: unknown) => {
      console.error('[Admin] Unable to verify the current session.', error);
      return of(router.createUrlTree(['/admin/login']));
    }),
  );
};
