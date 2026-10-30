ubuntu@aquila$ uname -a
Linux aquila 5.15.0-198-generic #208-Ubuntu SMP Fri Sep 4 10:22:38 UTC 2026 x86_64 x86_64 x86_64 GNU/Linux

ubuntu@aquila$ cat /etc/os-release
PRETTY_NAME="Ubuntu 22.04.5 LTS"
NAME="Ubuntu"
VERSION_ID="22.04"
VERSION="22.04.5 LTS (Jammy Jellyfish)"
VERSION_CODENAME=jammy
ID=ubuntu
ID_LIKE=debian
HOME_URL="https://www.ubuntu.com/"
SUPPORT_URL="https://help.ubuntu.com/"
BUG_REPORT_URL="https://bugs.launchpad.net/ubuntu/"
PRIVACY_POLICY_URL="https://www.ubuntu.com/legal/terms-and-policies/privacy-policy"
UBUNTU_CODENAME=jammy

ubuntu@aquila$ hostnamectl
 Static hostname: aquila
       Icon name: computer-vm
         Chassis: vm
      Machine ID: 4f32d02cc2c24f0ab149a12bffcda1aa
         Boot ID: e52691d90b604aaca578d302e8945217
  Virtualization: kvm
Operating System: Ubuntu 22.04.5 LTS
          Kernel: Linux 5.15.0-198-generic
    Architecture: x86-64
 Hardware Vendor: Red Hat
  Hardware Model: OpenStack Nova

ubuntu@aquila$ php -v
PHP 8.4.27 (cli) (built: Oct 21 2026 08:18:37) (NTS)
Copyright (c) The PHP Group
Built by Ubuntu
Zend Engine v4.4.27, Copyright (c) Zend Technologies
    with Zend OPcache v8.4.27, Copyright (c), by Zend Technologies

ubuntu@aquila$ php bin/console --version
Symfony v7.4.22 (env: prod, debug: false)