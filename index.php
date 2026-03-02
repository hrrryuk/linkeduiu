<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedUIU - an internal job portal for UIU</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Mulish:wght@200..1000&family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: "Poppins", sans-serif;
        }

        main {
            display: flex;
            column-gap: 20px;
        }

        section {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            width: 360px;
            padding: 30px 25px;
            box-shadow: rgba(67, 71, 85, 0.27) 0px 0px 0.25em,
                rgba(90, 125, 188, 0.05) 0px 0.25em 1em;
        }

        img {
            width: 90px;
            align-self: center;
        }

        h2 {
            font-size: 28px;
            font-weight: 500;
            text-align: center;
            margin: 10px 0 20px 0;
        }

        form {
            display: flex;
            flex-direction: column;
            row-gap: 15px;
        }

        input,
        button {
            padding: 10px 20px;
            border: 1px solid #f45136;
            border-radius: 0;
            outline: none;
            font-size: 14px;
        }

        input::placeholder {
            opacity: 0.8;
        }

        button {
            background: linear-gradient(-135deg, #d67c2f 0%, #f45136 100%);
            color: white;
            cursor: pointer;
            margin: 5px 0 15px 0;
        }

        div {
            display: flex;
            justify-content: center;
            column-gap: 5px;
            font-size: 14px;
        }

        span {
            opacity: 0.6;
        }

        a {
            font-weight: 500;
            color: #f45136;
            text-decoration: none;
            cursor: pointer;
        }

        #register {
            display: none;
        }
    </style>
</head>

<body>
    <main>
        <section id="login">
            <img src="/uploads/img/uiu_logo.png">
            <h2>Job Portal: Login</h2>
            <form action="login.php" method="post">
                <input type="text" name="username" placeholder="Enter your id / initial" required>
                <input type="password" name="password" placeholder="Enter your password" required>
                <button type="submit" name="login">LOG IN</button>
            </form>
            <div><span>First visit?</span><a onclick="toggleForm()">register here</a></div>
        </section>
        <section id="register">
            <img src="/uploads/img/uiu_logo.png">
            <h2>Job Portal: Register</h2>
            <form action="register.php" method="post">
                <input type="text" name="username" placeholder="Enter your id / initial" required>
                <input type="password" name="password" placeholder="Create a password" required>
                <input type="password" name="confirm" placeholder="Confirm your password" required>
                <button type="submit" name="register">REGISTER</button>
            </form>
            <div><span>Have account?</span><a onclick="toggleForm()">login here</a></div>
        </section>
    </main>

    <!-- form toggle with animation -->
    <script>
        function toggleForm() {
            const login = document.getElementById("login");
            const register = document.getElementById("register");

            const fadeOut = (el, callback) => {
                el.style.transition = "opacity 0.2s linear";
                el.style.opacity = 0;
                setTimeout(() => {
                    el.style.display = "none";
                    if (callback) callback();
                }, 400);
            };

            const fadeIn = (el) => {
                el.style.display = "flex";
                el.style.opacity = 0;
                el.style.transition = "opacity 0.2s linear";
                requestAnimationFrame(() => {
                    el.style.opacity = 1;
                });
            };

            if (login.style.display !== "none") {
                fadeOut(login, () => fadeIn(register));
            } else {
                fadeOut(register, () => fadeIn(login));
            }
        }
    </script>
</body>

</html>