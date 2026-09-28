<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password · Pig World Smart</title>
    <style>
        :root{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#183426;background:#f5f8f4}*{box-sizing:border-box}body{display:grid;min-height:100vh;place-items:center;margin:0;padding:24px}.card{width:min(100%,460px);padding:34px;border:1px solid #dce7dc;border-radius:20px;background:#fff;box-shadow:0 20px 60px #18342612}.eyebrow{color:#28704c;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}h1{margin:10px 0;font-size:28px}p{color:#65766a;line-height:1.6}label{display:block;margin:18px 0 7px;font-size:13px;font-weight:700}input{width:100%;padding:13px;border:1px solid #cbd8cd;border-radius:10px;font:inherit}button{width:100%;margin-top:22px;padding:14px;border:0;border-radius:10px;color:#fff;background:#176b4d;font:inherit;font-weight:800;cursor:pointer}.error{margin-top:8px;color:#a33e3e;font-size:13px}.success{padding:14px;border-radius:10px;color:#267348;background:#e8f4e9}
    </style>
</head>
<body>
<main class="card">
    <div class="eyebrow">Pig World Smart</div>
    <h1>{{ $success ? 'Password updated' : 'Reset your password' }}</h1>
    @if ($success)
        <p class="success">{{ $status }}</p>
    @else
        <p>Choose a new password for your account. Reset links expire after 60 minutes.</p>
        @if ($status)<p class="error">{{ $status }}</p>@endif
        @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            <button type="submit">Save new password</button>
        </form>
    @endif
</main>
</body>
</html>
