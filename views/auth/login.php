<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '') ?>">
    <title>DockerPanel — Вход</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a1a;
            --bg-card: rgba(20, 20, 50, 0.6);
            --bg-glass-border: rgba(255, 255, 255, 0.08);
            --bg-input: rgba(255, 255, 255, 0.05);
            --text-primary: #e8e8f0;
            --text-secondary: #8888aa;
            --accent-primary: #00d4ff;
            --accent-gradient: linear-gradient(135deg, #00d4ff 0%, #7c3aed 100%);
            --accent-gradient-hover: linear-gradient(135deg, #00e5ff 0%, #8b5cf6 100%);
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(ellipse at 20% 50%, rgba(0, 212, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 20%, rgba(124, 58, 237, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--bg-glass-border);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            z-index: 1;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideUp {
            to { transform: translateY(0); opacity: 1; }
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: var(--accent-gradient);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 16px;
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        .logo h1 { font-size: 24px; font-weight: 700; }
        .logo p { color: var(--text-secondary); font-size: 14px; margin-top: 8px; }

        .form-group { margin-bottom: 20px; }
        
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--bg-glass-border);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--accent-gradient);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.2);
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: var(--accent-gradient-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 212, 255, 0.3);
        }

        .error-msg {
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">🐳</div>
            <h1>DockerPanel</h1>
            <p>Вход в панель управления</p>
        </div>
        
        <div class="error-msg" id="error-box"></div>
        
        <form id="login-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            
            <div class="form-group">
                <label class="form-label">Пользователь</label>
                <input type="text" name="username" class="form-control" required autofocus placeholder="admin">
            </div>
            
            <div class="form-group">
                <label class="form-label">Пароль</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>
            
            <button type="submit" class="btn-submit">Войти</button>
        </form>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('.btn-submit');
            const errBox = document.getElementById('error-box');
            
            btn.disabled = true;
            btn.textContent = 'Вход...';
            errBox.style.display = 'none';

            try {
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData.entries());

                const res = await fetch('/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': data.csrf_token
                    },
                    body: JSON.stringify(data)
                });

                const result = await res.json();
                
                if (result.success) {
                    btn.textContent = 'Успешно!';
                    window.location.href = result.data?.redirect || '/dashboard';
                } else {
                    throw new Error(result.message || 'Ошибка авторизации');
                }
            } catch (err) {
                errBox.textContent = err.message;
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Войти';
            }
        });
    </script>
</body>
</html>
