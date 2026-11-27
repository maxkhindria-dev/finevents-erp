<?php

/*
| Guards the "no secrets in the repo" rule from CLAUDE.md.
| (gitleaks in CI is the main protection; this catches the obvious mistakes early.)
*/

it('keeps .env.example free of real values for secrets', function () {
    $lines = file(base_path('.env.example'), FILE_IGNORE_NEW_LINES);

    $secretNames = '/^(APP_KEY|DB_PASSWORD|REDIS_PASSWORD|MAIL_PASSWORD|AWS_SECRET_ACCESS_KEY|GOOGLE_CLIENT_SECRET|FIELD_ENCRYPTION_KEY|AUDIT_CHAIN_KEY|DB_SENSITIVE_PASSWORD|DB_MIGRATOR_PASSWORD)=(.*)$/';

    foreach ($lines as $line) {
        if (preg_match($secretNames, $line, $m)) {
            expect(in_array($m[2], ['', 'null'], true))
                ->toBeTrue("{$m[1]} in .env.example must be empty");
        }
    }
});

it('ignores every env file except the example in git', function () {
    $gitignore = file_get_contents(base_path('.gitignore'));

    expect($gitignore)->toContain("\n.env\n")
        ->toContain('.env.*')
        ->toContain('!.env.example');
});
