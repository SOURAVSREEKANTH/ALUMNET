<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Session;
use AlumniConnect\Support\View;

final class PageController
{
    public function home(): void
    {
        View::render('pages/home', [
            'pageTitle' => 'AlumniConnect — Your college. Your people. Your next chapter.',
            'isHome' => true,
        ]);
    }

    public function dashboard(): void
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('error', 'Sign in to open your dashboard.');
            View::redirect('/login');
        }

        View::render('pages/dashboard', [
            'pageTitle' => 'Your dashboard — AlumniConnect',
            'currentUser' => $user,
        ]);
    }
}
