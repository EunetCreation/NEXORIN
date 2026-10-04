<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Select Workspace | NEXORIN</title>
    <style>
        :root{color-scheme:dark;font-family:Inter,ui-sans-serif,system-ui,sans-serif}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#020c16;color:#eefbff;padding:32px}
        main{width:min(720px,100%);padding:40px;border:1px solid #173348;border-radius:24px;background:linear-gradient(145deg,#071827,#04111d);box-shadow:0 32px 80px #0008}
        img{width:240px;max-width:70%;height:auto}h1{margin:32px 0 8px;font-size:34px}p{color:#91a5b6;line-height:1.7}
        form{display:grid;gap:12px;margin-top:28px}.workspace{display:flex;align-items:center;justify-content:space-between;gap:18px;width:100%;padding:18px 20px;border:1px solid #1b3a50;border-radius:14px;background:#071522;color:#fff;text-align:left;cursor:pointer}.workspace:hover,.workspace:focus-visible{border-color:#10c9dd;outline:none;transform:translateY(-1px)}small{color:#6f8799}
    </style>
</head>
<body>
<main>
    <img src="{{ asset('images/nexorin-wordmark.png') }}" alt="NEXORIN">
    <h1>Select your workspace</h1>
    <p>{{ $currentUser->name }}, your account belongs to multiple active companies. Choose the company context for this session. Your effective roles and permissions will be resolved from that membership.</p>
    <form method="POST" action="{{ route('workspace.activate') }}">
        @csrf
        @forelse ($companies as $company)
            <button class="workspace" type="submit" name="company_id" value="{{ $company->getKey() }}">
                <span><strong>{{ $company->name }}</strong><br><small>{{ $company->code ?: $company->slug }}</small></span>
                <span aria-hidden="true">→</span>
            </button>
        @empty
            <p>No active workspace is available. Contact a NEXORIN administrator.</p>
        @endforelse
    </form>
</main>
</body>
</html>
