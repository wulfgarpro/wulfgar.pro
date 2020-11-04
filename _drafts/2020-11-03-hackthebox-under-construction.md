---
layout: post
title: PenLog - Under Construction by HackTheBox
categories: [HackTheBox, Penlog, Write Up, CTF, Challenge, Tracks]
---

## Details

**Platform:** [HackTheBox](https://www.hackthebox.eu/)\
**Difficulty:** Medium\
**Link:** [Under Construction](https://app.hackthebox.eu/challenges/111)

## Enumeration

With the instance started and the challenge package downloaded, navigate to the web app:

![under-construction](/images/posts/penlog_under_construction_by_hackthebox/under_construction.png)
_(**Note:**: The IP address and port is supplied via the challenge "Start instance" page.)_

Enter a "test" user username and password and click register: _test:test_.

![register](/images/posts/penlog_under_construction_by_hackthebox/register.png)

Start `Burp Suite` proxy and configure the browser's HTTP proxy:

![firefox-proxy](/images/posts/penlog_under_construction_by_hackthebox/firefox_proxy.png)

With `Burp` intercepting via the configured proxy, login to the web app with the registered test account:

![login](/images/posts/penlog_under_construction_by_hackthebox/login.png)

Via `Burp`, "Forward" the login request:

![burp-login-request](/images/posts/penlog_under_construction_by_hackthebox/burp_login_request.png)

Again, via `Burp`, send the redirect request, including the "Cookie: session=" token to `Burp`'s "Repeater":

![burp-redirect-request](/images/posts/penlog_under_construction_by_hackthebox/burp_redirect_request.png)
_(**Note:**: Press ctrl-R to send the request to `Burp`'s repeater.)_

Perusing the provided `expressjs` web app's source code, the above session cookie, or, "token", is generated and sent as a HTTP redirect response in the route file "routes/index.js":

![login-token](/images/posts/penlog_under_construction_by_hackthebox/login_token.png)

The `JWTHelper.sign(...)` call leads to the file "helpers/JWTHelper.js" which includes a `requires` for "jsonwebtokens":

![requires-jsonwebtoken](/images/posts/penlog_under_construction_by_hackthebox/requires_jsonwebtoken.png)

### JSON Web Token (JWT)

Googling "jsonwebtoken vulnerabilities", this library is vulnerable to "Authentication Bypass" as described [here](https://snyk.io/test/npm/jsonwebtoken/4.0.0#npm:jsonwebtoken:20150331).

This vulnerability is known as [CVE-2015-9235](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2015-9235) and describes "JWT HS/RSA key confusion vulnerability" where the vulnerable server side successfully verifies a token by erroneously:
1. Expecting "RSA" (public-private key scheme), but instead receiving "HSA256"(symmetric-key scheme); _an attacker can easily forge a token with an updated "alg"_
2. Blindly passing the re-purposed public-key as the verification key to the server side verification method, confusing the _known_ public-key as the verification key for HS256

Copy the JWT token from the session cookie in `Burp`'s repeater, and use [jwt.io](https://jwt.io) to decode it. Note the "alg" type "RS256" and that the payload data includes a public key element "pk":

![token-decoded](/images/posts/penlog_under_construction_by_hackthebox/token_decoded.png)

As shown above, the decoded JWT token's header designates "RS256" as the algorithm and the payload divulges the server-side public-key - the two requirements for CVE-2015-9235.

Download [jwt_forge.py](https://gist.github.com/wulfgarpro/3e87ae77a7107a3e3a2453eb38a3de20) locally and copy the encoded JWT token from `Burp`'s repeater to a file for easier management, e.g. "jwt_token_example.txt". Execute `jwt_forge.py` using a subshell to pass in the copied JWT token and supply the registered username "test":

![jwt-forge-keyconfusion-test-1](/images/posts/penlog_under_construction_by_hackthebox/jwt_forge_keyconfusion_test_1.png)
_(**Note:** I wrote `jwt_forge.py` to learn about JWT tokens and solve the "Under Construction" challenge.)_

Copy the "forged" token from the terminal and replace the current token in `Burp`'s repeater window; send the request to the web app using and confirm the user is still authenticated as "test":

![jwt-forge-keyconfusion-test-2](/images/posts/penlog_under_construction_by_hackthebox/jwt_forge_keyconfusion_test_2.png)

### SQL Injection (SQLi)

Spending more time reading the source code, there also looks to be a simple SQL injection in "helpers/DBHelper.js" which interfaces to an `SQLite3` database whereby the user supplied input variable `${username}` is concatenated with the SQL "SELECT" statement in the `DBHelper.getUser` helper:

![login-token](/images/posts/penlog_under_construction_by_hackthebox/sqli_username.png)

The injection point, `DBHelper.getUser`, is called via the result of an async call from "middleware/AuthMiddleware.js", as highlighted in "routes/index.js":

![get-user-call](/images/posts/penlog_under_construction_by_hackthebox/get_user_call.png)

Here `req.data.username`, the parameter passed to `DBHelper.getUser` is the username from the JWT token, extracted as of middleware `AuthMiddleware`:

![auth-middleware](/images/posts/penlog_under_construction_by_hackthebox/auth_middleware.png)

Re-forge the last "forged" JWT token using `jwt_forge.py`, this time passing a UNION injected "username" to get the version of `Sqlite3`:







## Flag

