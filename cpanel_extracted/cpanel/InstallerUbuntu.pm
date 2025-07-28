package InstallerUbuntu;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.

use strict;
use warnings;

use CpanelLogger;

use CpanelMySQL ();

use Installer ();
our @ISA = qw/Installer/;

sub distro_type { return 'debian' }

# We use this in the usrmerge check oddly enough to block non 20/22 installs
# so thus can't remove it (well, we could, but no point in replacing it with
# an array really).
use constant CPANEL_UBUNTU_SUPPORT => [qw{20 22}];

sub check_system_support {
    my ($self) = @_;

    $self->SUPER::check_system_support;

    my $distro_name  = $self->distro_name;
    my $distro_major = $self->distro_major;

    if ( $distro_name ne 'ubuntu' ) {
        return $self->invalid_system("cPanel, L.L.C. does not support $distro_name for new installations.");
    }

    return;
}

sub check_networking_scripts {

    # We don't do this check on ubuntu??
    return;
}

sub check_system_files {
    my ($self) = @_;

    $self->SUPER::check_system_files;

    # Unset the kernel flag that prevents even root from accessing other users files in /tmp
    Common::ssystem( "sysctl", "--ignore", "fs.protected_regular=0" );

    # Then configure this to be the default upon reboot
    my $prot_file = '/usr/lib/sysctl.d/protect-links.conf';
    my @orig_prot_file_contents;
    if ( -f $prot_file ) {
        open( my $prot_rd_fh, '<', $prot_file );
        while (<$prot_rd_fh>) {
            push( @orig_prot_file_contents, $_ );
        }
        close($prot_rd_fh);
        open( my $prot_wr_fh, '>', $prot_file );
        foreach my $line (@orig_prot_file_contents) {
            if ( $line =~ m/fs\.protected_regular/ ) {
                print $prot_wr_fh "fs.protected_regular = 0\n";
            }
            else {
                print $prot_wr_fh "$line";
            }
        }
        close($prot_wr_fh);
    }
    verify_usrmerge_is_installed();

    # Configure alternate temp dir for debconf since /tmp is often mounted noexec
    mkdir '/root/tmp';
    open( my $debconf_fh, '>>', '/etc/apt/apt.conf.d/50extracttemplates' );
    print $debconf_fh "APT\n{\n ExtractTemplates\n\t\{\n\t\tTempDir /root/tmp;\n\t};\n};\n";
    close($debconf_fh);

    my $out = `/usr/bin/dpkg -s libc6 2>&1`;    ## no critic(ProhibitQxAndBackticks)
    if ( $out !~ m/Installed\-Size:\s/ ) {
        ERROR( q{Your operating system's package update method } . qq{(apt) could not locate the libc6 package. } . q{This is an indication of an improper setup. } . q{You must correct this error before you proceed. } );
        DEBUG($out);
        FATAL("\n\n");
    }

    # This package is needed for File::FcntlLock, used by installd/apt-get-wait
    $self->apt_nohang_ssystem( '/usr/bin/apt-get', 'install', '-y', 'libfile-fcntllock-perl' );

    return;
}

# This system clobbers resolv.conf even if you update it manually.
sub setup_and_check_resolv_conf {
    my ($self) = @_;

    print "Disabling systemd-resolved if it is enabled...";
    my $needs_action = `systemctl list-unit-files | grep systemd-resolved`;

    # Remove the stub resolver and put a viable one in place
    if ($needs_action) {
        Common::ssystem(qw{systemctl disable --now systemd-resolved});
        unlink '/etc/resolv.conf';
        if ( open( my $fh, '>', '/etc/resolv.conf' ) ) {
            print $fh "nameserver 1.1.1.1\nnameserver 8.8.8.8\n";
            close($fh);
        }
        else {
            WARN( 'Could not create new /etc/resolv.conf : ' . $! );
        }
    }

    return $self->SUPER::setup_and_check_resolv_conf;
}

sub install_basic_precursor_packages {
    my ($self) = @_;

    INFO("Installing packages needed to download and run the cPanel initial install.");

    # Assure wget/bzip2/gpg are installed for centhat. These packages are needed prior to sysup
    my @packages_to_install = qw/wget bzip2 gpg-agent xz-utils nscd psmisc python3 rdate cron sysstat net-tools debconf-utils libnet-ssleay-perl/;

    $self->apt_nohang_ssystem( './apt-get-wait', '-y', 'install', @packages_to_install );

    return;
}

# we need to call update first to ensure we have a full package list, otherwise it won't be able to find packages for install
sub update_apt {
    my ($self) = @_;
    return unless $self->{'update_apt'}++ == 0;    # Run once.
    $self->apt_nohang_ssystem( '/usr/bin/apt-get', 'update' );
    return;
}

sub apt_nohang_ssystem {
    my ( $self, @cmd ) = @_;

    $self->update_apt;                             #circular but it's ok because we bumped $update_apt already.

    my $failcount = 0;
    my $result    = 1;
    while ($result) {                              # While apt is failing.
        $result = Common::ssystem(@cmd);
        last if ( !$result );                      # apt came back clean. Stop re-trying

        $failcount++;
        if ( $failcount > 5 ) {
            FATAL("apt failed $failcount times. The installation process cannot continue.");
        }
    }

    return;
}

sub remove_distro_software {
    my ($self) = @_;

    my @remove_pkgs = qw(
      apache2-data
      apache2-utils
      dovecot-core
      dovecot-imapd
      dovecot-lmtpd
      dovecot-pop3d
      exim4
      exim4-base
      exim4-config
      exim4-daemon-heavy
      exim4-daemon-light
      exim4-dev
      exim4-doc-html
      exim4-doc-info
      mysql-server
      mysql-server-8.0
      mysql-server-core-8.0
      mysql-common
      libmysqlclient21
      mysql-client
      mysql-client-8.0
      mysql-client-core-8.0
      portreserve
      postfix
      sendmail
      spamassassin
      libapache2-mod-perl2
      mariadb-client
      mariadb-client-10.3
      mariadb-client-core-10.3
      mariadb-common
      mariadb-plugin-connect
      mariadb-server
      mariadb-server-10.3
      mariadb-server-core-10.3
      mariadb-test
      mycli
      pure-ftpd
      proftpd-basic
    );

    INFO('Ensuring that conflicting services are not installed...');
    Common::ssystem( '/usr/bin/apt-get', '-y', 'purge', @remove_pkgs, { ignore_errors => 1 } );

    return;
}

sub verify_mysql_version {
    my ( $self, $cpanel_config ) = @_;

    my $mysql_version = $cpanel_config->{'mysql-version'};

    return unless length $mysql_version;

    # The only supported installable versions are:
    # 8.0, 8.4, 10.5, 10.6, 10.11, 11.4
    my $supported_versions = qr{^(?:
        | 8(?:\.[04])?
        | 10(?:\.(?:[5-6]|11))
        | 11(?:\.(?:[4]))
    )$}x;
    unless ( $mysql_version =~ $supported_versions ) {
        FATAL('The mysql-version value in /root/cpanel_profile/cpanel.config is either invalid or references an unsupported MySQL/MariaDB version. See https://go.cpanel.net/supported-mysql-mariadb-versions for a list of supported versions.');
    }

    my $lts_version = $self->lts_version;

    if ( CpanelMySQL::version_is_mariadb($mysql_version) && $lts_version < 117 ) {
        FATAL("cPanel & WHM version $lts_version does not support MariaDB® on Ubuntu®. See https://go.cpanel.net/supported-mysql-mariadb-versions for a list of supported versions.");
    }

    my $distro_major = $self->distro_major;

    my %db_id = CpanelMySQL::get_db_identifiers($mysql_version);

    my $advice = CpanelMySQL::get_db_version_advice( $mysql_version, $lts_version, $distro_major, $db_id{'plain'} );

    if ( $advice->{ver} && $advice->{action} ) {
        FATAL("You must set $db_id{'stylized'} to version $advice->{ver} or $advice->{action} in the /root/cpanel_profile/cpanel.config file for cPanel & WHM version $lts_version.");
    }

    return;
}

# These packages are needed for MySQL later in the install
# By downloading them now we do not have to wait for them later
sub background_download_packages_used_during_initial_install {
    my ($self) = @_;

    my @sysup_packages_to_install = qw{quota expat libexpat1-dev};
    my @ea4_packages_to_install   = qw{elinks libssh2-1 libssh2-1-dev libvpx-dev libwww-perl libkrb5-dev libcompress-raw-bzip2-perl libcompress-raw-zlib-perl autoconf automake};
    push @ea4_packages_to_install, ( int( $self->distro_major ) < 22 ? 'libvpx6' : 'libvpx7' );
    my @mysql_support_packages_to_install = qw{libnuma1 grep libuser coreutils libdbi-perl};
    my @packages_to_install               = ( @mysql_support_packages_to_install, @sysup_packages_to_install, @ea4_packages_to_install );

    return $self->run_in_background( sub { $self->apt_nohang_ssystem( './apt-get-wait', '--download-only', '-y', 'install', @packages_to_install ); } );
}

sub verify_usrmerge_is_installed {
    my $required_symlinks = {
        '/bin'  => 'usr/bin',
        '/sbin' => 'usr/sbin',
        '/lib'  => 'usr/lib',
    };

    foreach my $link ( keys %{$required_symlinks} ) {
        my $target = readlink $link;
        if ( !length $target || $target ne $required_symlinks->{$link} ) {
            my $ubuntu_major_csv = join( ", ", map { "$_.04" } @{ CPANEL_UBUNTU_SUPPORT() } );
            my $errmsg           = "You can only install cPanel & WHM on a fresh Ubuntu installation of the following versions: $ubuntu_major_csv";
            FATAL($errmsg);
        }
    }
    return;
}

1;
