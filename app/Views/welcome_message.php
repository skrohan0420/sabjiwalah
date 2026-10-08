<!DOCTYPE html>
<html lang="en">
<head>
    <?= view('shared/favicon') ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sabjiwalah web application">
    <title>Sabjiwalah</title>
    <style {csp-style-nonce}>
        :root {
            color-scheme: light;
            font-family: Arial, Helvetica, sans-serif;
            color: #172018;
            background: #f7f4ed;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px;
        }

        main {
            width: min(720px, 100%);
            padding: 40px;
            border: 1px solid #d8dfd0;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 20px 60px rgba(23, 32, 24, 0.08);
        }

        .eyebrow {
            margin: 0 0 12px;
            color: #4f6f35;
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(2.25rem, 7vw, 4.5rem);
            line-height: 1;
        }

        p {
            margin: 20px 0 0;
            max-width: 56ch;
            color: #4f5a50;
            font-size: 1.05rem;
            line-height: 1.6;
        }

        code {
            padding: 2px 6px;
            border-radius: 4px;
            background: #eef4e7;
            color: #28451f;
            font-size: 0.95em;
        }

        .status {
            margin-top: 28px;
            padding: 18px;
            border: 1px solid #d8dfd0;
            border-radius: 8px;
            background: #f8fbf4;
        }

        .status--error {
            border-color: #e5b8b8;
            background: #fff6f6;
        }

        .status h2 {
            margin: 0;
            font-size: 1.1rem;
        }

        .status dl {
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: 8px 16px;
            margin: 16px 0 0;
        }

        .status dt {
            color: #59645a;
            font-weight: 700;
        }

        .status dd {
            margin: 0;
            color: #172018;
        }
    </style>
</head>
<body>
    <main>
        <p class="eyebrow">CodeIgniter <?= CodeIgniter\CodeIgniter::CI_VERSION ?></p>
        <h1>Sabjiwalah</h1>
        <p>The project is ready for application development. This page is rendered from <code>app/Views/welcome_message.php</code> through <code>App\Controllers\Home</code>.</p>

        <?php $databaseStatus = $databaseStatus ?? ['connected' => false, 'message' => 'Database status was not checked.']; ?>
        <section class="status<?= $databaseStatus['connected'] ? '' : ' status--error' ?>">
            <h2>Database <?= $databaseStatus['connected'] ? 'connected' : 'not connected' ?></h2>
            <p><?= esc($databaseStatus['message']) ?></p>

            <?php if ($databaseStatus['connected']) : ?>
                <dl>
                    <dt>Database</dt>
                    <dd><?= esc($databaseStatus['database']) ?></dd>
                    <dt>User</dt>
                    <dd><?= esc($databaseStatus['user']) ?></dd>
                    <dt>Driver</dt>
                    <dd><?= esc($databaseStatus['driver']) ?></dd>
                </dl>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
