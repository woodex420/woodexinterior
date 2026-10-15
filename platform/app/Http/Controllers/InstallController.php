<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\System\Actions\CreateOwnerAccount;
use App\Support\Install\Installer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use RuntimeException;
use Throwable;

/**
 * Web installer (D-58). Its routes run without sessions, cookies or CSRF (nothing exists yet): the setup code from the
 * private data folder is the proof of ownership on every POST. Once the installation finishes the code is deleted and
 * every installer URL answers 404.
 */
final class InstallController
{
    public function __construct(private readonly Installer $installer) {}

    public function show(Request $request): Response
    {
        if ($this->installer->site()->isConfigured()) {
            abort_unless($this->installer->hasCode(), 404);

            return $this->finishPage(code: '', auto: false);
        }

        try {
            $this->installer->ensureCode();
        } catch (RuntimeException $exception) {
            return $this->form($request, new MessageBag(['setup' => $exception->getMessage()]), 500);
        }

        return $this->form($request);
    }

    public function store(Request $request): Response
    {
        abort_if($this->installer->site()->isConfigured(), 404);

        $validator = Validator::make($request->all(), [
            'setup_code' => ['required', 'string', 'max:64'],
            'app_url' => ['required', 'url:http,https', 'max:190'],
            'connection' => ['required', 'in:mysql,sqlite'],
            'db_host' => ['exclude_unless:connection,mysql', 'required', 'string', 'max:190'],
            'db_port' => ['exclude_unless:connection,mysql', 'required', 'integer', 'between:1,65535'],
            'db_database' => ['exclude_unless:connection,mysql', 'required', 'string', 'max:64'],
            'db_username' => ['exclude_unless:connection,mysql', 'required', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:190'],
            ...CreateOwnerAccount::rules(),
            'owner_password' => [...CreateOwnerAccount::rules()['owner_password'], 'confirmed'],
        ], [
            'owner_password.confirmed' => 'The two owner passwords do not match.',
        ], [
            'app_url' => 'website address', 'db_host' => 'database host', 'db_port' => 'database port',
            'db_database' => 'database name', 'db_username' => 'database user', 'owner_name' => 'owner name',
            'owner_email' => 'owner email', 'owner_password' => 'owner password',
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (! $this->installer->codeMatches((string) $request->input('setup_code'))) {
                $validator->errors()->add('setup_code', 'The setup code does not match. Open the file named above in hPanel → File Manager (Access all files of hosting) and copy the code again.');
            }

            foreach (['app_url', 'db_host', 'db_database', 'db_username', 'db_password', 'owner_name'] as $field) {
                if (preg_match('/[\r\n\0]/', (string) $request->input($field)) === 1) {
                    $validator->errors()->add($field, 'Line breaks are not allowed here.');
                }
            }
        });

        if ($validator->fails()) {
            return $this->form($request, $validator->errors(), 422);
        }

        $database = [
            'connection' => (string) $request->input('connection'),
            'host' => trim((string) $request->input('db_host', 'localhost')),
            'port' => (int) $request->input('db_port', 3306),
            'database' => trim((string) $request->input('db_database')),
            'username' => trim((string) $request->input('db_username')),
            'password' => (string) $request->input('db_password', ''),
        ];

        $check = $this->installer->checkDatabase($database);

        if (! $check['ok']) {
            return $this->form($request, new MessageBag(['database' => $check['message']]), 422);
        }

        try {
            $this->installer->configure([
                'app_url' => (string) $request->input('app_url'),
                ...$database,
                'owner_name' => trim((string) $request->input('owner_name')),
                'owner_email' => trim((string) $request->input('owner_email')),
                'owner_password' => (string) $request->input('owner_password'),
                'enforce_2fa' => $request->boolean('enforce_2fa'),
            ]);
        } catch (RuntimeException $exception) {
            return $this->form($request, new MessageBag(['setup' => $exception->getMessage()]), 500);
        }

        return $this->finishPage(code: (string) $request->input('setup_code'), auto: true);
    }

    public function finish(Request $request, CreateOwnerAccount $createOwner): Response
    {
        abort_unless($this->installer->site()->isConfigured() && $this->installer->hasCode(), 404);

        $code = (string) $request->input('setup_code');

        if (! $this->installer->codeMatches($code)) {
            return $this->finishPage(code: '', auto: false, error: 'The setup code does not match. Copy it again from the file in the data folder.', status: 422);
        }

        try {
            $result = $this->installer->finish($createOwner);
        } catch (Throwable $exception) {
            report($exception);

            return $this->finishPage(code: $code, auto: false, error: $exception->getMessage(), status: 500);
        }

        return response()->view('install.done', [
            'owner' => $result['owner'],
            'log' => $result['log'],
            'cron' => $this->installer->cronCommand(),
            'envFile' => $this->installer->site()->displayPath($this->installer->directory().'/.env'),
            'classic' => $this->installer->isClassic(),
        ]);
    }

    private function form(Request $request, ?MessageBag $errors = null, int $status = 200): Response
    {
        $bag = new ViewErrorBag;

        if ($errors !== null) {
            $bag->put('default', $errors);
        }

        return response()->view('install.form', [
            'errors' => $bag,
            'requirements' => $this->installer->requirements(),
            'codeFile' => $this->installer->site()->displayPath($this->installer->codePath()),
            'siteCodeFile' => $this->installer->siteCodeDisplay(),
            'classic' => $this->installer->isClassic(),
            'input' => [
                'app_url' => (string) $request->input('app_url', $request->getSchemeAndHttpHost()),
                'connection' => (string) $request->input('connection', 'mysql'),
                'db_host' => (string) $request->input('db_host', 'localhost'),
                'db_port' => (string) $request->input('db_port', '3306'),
                'db_database' => (string) $request->input('db_database', ''),
                'db_username' => (string) $request->input('db_username', ''),
                'owner_name' => (string) $request->input('owner_name', ''),
                'owner_email' => (string) $request->input('owner_email', ''),
                'enforce_2fa' => $request->isMethod('post') ? $request->boolean('enforce_2fa') : true,
            ],
        ], $status);
    }

    private function finishPage(string $code, bool $auto, ?string $error = null, int $status = 200): Response
    {
        return response()->view('install.finish', [
            'code' => $code,
            'auto' => $auto && $error === null,
            'error' => $error,
            'codeFile' => $this->installer->site()->displayPath($this->installer->codePath()),
            'siteCodeFile' => $this->installer->siteCodeDisplay(),
        ], $status);
    }
}
