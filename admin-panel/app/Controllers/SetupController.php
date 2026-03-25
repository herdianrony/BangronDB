<?php

namespace App\Controllers;

use App\Services\SetupService;

class SetupController
{
    public function show(): void
    {
        $setup = new SetupService();
        if ($setup->isInstalled()) {
            redirect('/login');
        }

        render('setup/index', [
            'title' => 'Setup BangronDB Studio',
            'pageTitle' => 'Setup',
        ]);
    }

    public function submit(): void
    {
        $setup = new SetupService();
        if ($setup->isInstalled()) {
            redirect('/login');
        }

        verify_csrf();
        $name = trim((string) request_post('name'));
        $email = trim((string) request_post('email'));
        $password = (string) request_post('password');

        if ($name === '' || $email === '' || $password === '') {
            flash('error', 'All fields are required.');
            redirect('/setup');
        }

        $setup->install($name, $email, $password);
        flash('success', 'Setup completed. Please login.');
        redirect('/login');
    }
}
