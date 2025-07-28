package OSDetect;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.



use strict;
use warnings;

## NOTE: This code has been copied from Cpanel/OS/SysPerlBootstrap.pm with minor modifications.

=encoding utf-8

=head1 NAME

Cpanel::OS::SysPerlBootstrap - SysPerlBootstrap logic used by Cpanel::OS

=head1 DO NOT USE THIS MODULE DIRECTLY

This code is intended to be system and cpanel perl compatible and to be consumed only in very specific circumstances.

Instead use L<Cpanel::OS> directly, and as its POD outlines, do not do logic on the OS info to determine what it is you need.

=head1 FUNCTIONS

=head2 get_os_info ($iknowwhatimdoing)

Please use Cpanel::OS for your OS info needs.
This is an internal helper to Cpanel::OS to get the
- distro
- major
- minor
- build id

=cut

use constant CACHE_FILE        => "/var/cpanel/caches/Cpanel-OS";
use constant CACHE_FILE_CUSTOM => CACHE_FILE . '.custom';

sub get_os_info {
    my @os_info = _read_os_info_cache();
    return @os_info if @os_info;

    if ( -e '/etc/redhat-release' ) {
        @os_info = _read_redhat_release();
    }
    elsif ( -e '/etc/os-release' ) {    # Only Ubuntu doesn't provide redhat-release
        @os_info = _read_os_release();
    }

    my ( $distro, $major, $minor, $build ) = @os_info;

    if ( grep { !length $_ } ( $distro, $major, $minor, $build ) ) {
        die sprintf( "Could not determine OS info (distro: %s, major: %s, minor: %s, build: %s)\n", $distro // '', $major // '', $minor // '', $build // '' );
    }

    return ( $^O, $distro, $major, $minor, $build );
}

=head2 clear(%opts)

Clear the /var/cpanel/caches/Cpanel-OS file
(optionally the .custom file too)

    clear();
    clear( custom => 1 );

=cut

sub clear {
    my (%opts) = @_;

    unlink CACHE_FILE;
    unlink CACHE_FILE_CUSTOM if $opts{custom};

    return;
}

=head2 _read_os_info_cache()

Read the previous cached values from /var/cpanel/caches/Cpanel-OS

=cut

sub _read_os_info_cache {

    my $cpanel_os_cache_file = CACHE_FILE;

    # If we've cached the information, just use it.
    my $cache_mtime = ( lstat $cpanel_os_cache_file )[9] or return;

    my $custom_os = readlink "$cpanel_os_cache_file.custom";

    # Do we need to cache bust?
    if ( !$custom_os ) {
        my $os_rel_mtime = ( stat("/etc/os-release") )[9];
        $os_rel_mtime //= ( stat("/etc/redhat-release") )[9];    # in the case of cloudlinux 6, we check against this instead

        # Bail out only if one of the release files is present since the cache file is suddenly our only valid source of truth.
        return if ( defined($os_rel_mtime) && $cache_mtime <= $os_rel_mtime );
    }

    return split /\|/, readlink($cpanel_os_cache_file);
}

=head2 _read_os_release()

Internal helper to read /etc/os-release

=cut

sub _read_os_release {
    open( my $os_fh, "<", "/etc/os-release" ) or die "Could not open /etc/os-release for reading: $!\n";

    my ( $distro, $ver, $ver_id );
    while ( my $line = <$os_fh> ) {
        my ( $key, $value ) = split( qr/\s*=\s*/, $line, 2 );
        chomp $value;
        $value =~ s/\s.+//;
        $value =~ s/"\z//;
        $value =~ s/^"//;

        if ( !$distro && $key eq "ID" ) {
            $distro = $value;
        }
        elsif ( !$ver_id && $key eq "VERSION_ID" ) {
            $ver_id = $value;
        }
        elsif ( !$ver && $key eq "VERSION" ) {
            $ver = $value;
        }

        last if length $distro && length $ver && length $ver_id;
    }
    close $os_fh;

    # ver_id is often enough.
    my ( $major, $minor, $build ) = split( qr/\./, $ver_id );
    return unless $distro;    # We have to at a minimum have a distro name. All hope is lost otherwise.

    unless ( length $major && length $minor && length $build ) {
        my ( $ver_major, $ver_minor, $ver_build ) = split( qr/\./, $ver );
        $major //= $ver_major;
        $minor //= ( $ver_minor // 0 );
        $build //= ( $ver_build // 0 );
    }

    return ( $distro, $major, $minor, $build );
}

=head2 _read_redhat_release()

Internal helper to read /etc/redhat-release

=cut

sub _read_redhat_release {
    open( my $cr_fh, "<", "/etc/redhat-release" ) or die "Could not open /etc/redhat-release for reading: $!\n";
    my $line = <$cr_fh>;
    chomp $line;

    my ($distro) = $line =~ m/^(\w+)/i;
    $distro = lc($distro);
    $distro = 'rhel' if $distro eq 'red';

    my ( $major, $minor, $build ) = $line =~ m{\b([0-9]+)\.([0-9]+)\.([0-9]+)};
    if ( !$major ) {
        ( $major, $minor ) = $line =~ m{\b([0-9]+)\.([0-9]+)};
    }
    if ( !$major ) {
        ($major) = $line =~ m{\b([0-9]+)};
    }
    $minor //= 0;
    $build //= 0;

    return ( $distro, $major, $minor, $build );
}

1;
