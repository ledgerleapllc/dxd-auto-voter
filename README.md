## DXD Auto Voter

### Setup

```bash
cd ~
git clone https://github.com/ledgerleapllc/dxd-auto-voter.git
cd dxd-auto-voter
sudo apt update
sudo apt -y install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt-get install -y php8.1
sudo apt-get install -y php8.1-{bcmath,gd,mbstring,common,curl}
```

### Configure

Copy **accounts.example.json** to **accounts.json** and add accounts there. Include email/passwords under "accounts" array in the following format,

```bash
cp accounts.example.json accounts.json
nano accounts.json
```

```json
"accounts": [
  {
    "email":    "example@test.com",
    "password": "password123"
  },
  ...
]
```

This file is ignored by github.

### Run Auto Voter

```bash
php main.php
```

Script will load accounts from **accounts.json** and cast a "for" vote valued at 0.01 rep on all available ballots for each account.

Create a daily cronjob for fully autonomous voting.

```
0 12 * * * php ~/dxd-auto-voter/main.php
```