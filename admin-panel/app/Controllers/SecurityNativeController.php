<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\SecurityNativeService;

class SecurityNativeController extends BaseController
{
    private SecurityNativeService $security;
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->security = new SecurityNativeService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        $this->requirePermission('audit.read');
        render('security/index', [
            'title' => 'Security',
            'pageTitle' => 'Security',
            'pageSubtitle' => 'Native BangronDB security controls',
            'navActive' => 'security',
            'user' => $this->authService->currentUser(),
            'status' => $this->security->status(),
        ]);
    }

    public function status(): void
    {
        $this->requirePermission('audit.read');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->security->status());
    }

    public function rotateKey(): void
    {
        $this->requirePermission('audit.read');
        verify_csrf();
        $user = $this->authService->currentUser();
        $result = $this->security->rotateKey($user['_id'] ?? 'system');
        $this->audit->log($user, 'security.key.rotate', 'security');
        flash('success', 'Security key rotated.');
        redirect('/security');
    }

    public function savePolicy(): void
    {
        $this->requirePermission('audit.read');
        verify_csrf();
        $user = $this->authService->currentUser();
        $name = trim((string) request_post('name'));
        $rules = json_decode((string) request_post('rules_json', '{}'), true);
        if ($name === '' || !is_array($rules)) {
            flash('error', 'Invalid policy payload.');
            redirect('/security');
        }
        $this->security->savePolicy($name, $rules, $user['_id'] ?? 'system');
        $this->audit->log($user, 'security.policy.save', 'security', $name);
        flash('success', 'Policy saved.');
        redirect('/security');
    }
}

