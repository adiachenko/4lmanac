<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ config("app.name") }}</title>

        <style>
            :root {
                color-scheme: light;
                font-family:
                    'Avenir Next', Avenir, 'Helvetica Neue', Helvetica, Arial,
                    sans-serif;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background:
                    linear-gradient(
                        120deg,
                        rgb(255 173 103 / 16%) 0%,
                        transparent 38%
                    ),
                    linear-gradient(
                        250deg,
                        rgb(99 102 241 / 11%) 0%,
                        transparent 34%
                    ),
                    radial-gradient(
                        130% 90% at 50% -10%,
                        #fff8e8 0%,
                        #f6f1e6 50%,
                        #efede8 100%
                    );
                color: #171717;
            }

            main {
                margin: 0;
                padding: 1rem 1.25rem;
                font-size: clamp(1.2rem, 1.6vw, 1.7rem);
                font-weight: 600;
                letter-spacing: -0.01em;
                color: #191919;
                text-wrap: balance;
            }
        </style>
    </head>
    <body>
        <main>Yep, it’s alive. 👀</main>
    </body>
</html>
