<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $broadcast->title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 32px 16px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #7B00FF 0%, #4F46E5 100%);
            padding: 28px 24px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        .header .subtitle {
            margin-top: 4px;
            font-size: 12px;
            opacity: 0.85;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .body {
            padding: 32px 24px;
        }
        .greeting {
            font-size: 15px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 16px;
        }
        .notice-card {
            background-color: #f1f5f9;
            border-left: 4px solid #7B00FF;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .notice-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 8px 0;
        }
        .notice-message {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            white-space: pre-line;
            margin: 0;
        }
        .meta-box {
            font-size: 12px;
            color: #64748b;
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 24px;
        }
        .action-button {
            display: inline-block;
            background-color: #7B00FF;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 10px;
            margin-top: 24px;
            text-align: center;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 24px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1>{{ $companyName }}</h1>
                <div class="subtitle">Official System Announcement</div>
            </div>

            <!-- Body -->
            <div class="body">
                <div class="greeting">
                    Hello {{ $recipient->full_name ?: $recipient->name }},
                </div>

                <div class="notice-card">
                    <h2 class="notice-title">{{ $broadcast->title }}</h2>
                    <p class="notice-message">{{ $broadcast->message }}</p>
                </div>

                <div class="meta-box">
                    <strong>Branch Scope:</strong> {{ $branchName }}<br>
                    <strong>Sent:</strong> {{ now()->toDayDateTimeString() }}
                </div>

                <div style="text-align: center;">
                    <a href="{{ $loginUrl }}" class="action-button">
                        Open POS Terminal &rarr;
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                This is an automated notification from {{ $companyName }}.<br>
                For support, contact <a href="mailto:{{ $supportEmail }}" style="color: #64748b;">{{ $supportEmail }}</a>.
            </div>
        </div>
    </div>
</body>
</html>
