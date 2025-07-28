package Common;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.

use strict;
use warnings;

use IO::Handle ();
use IO::Select ();
use IPC::Open3 ();
use HTTP::Tiny ();    # Fatpacked.

use CpanelLogger;     # Must import!
use CpanelGPG    ();
use CpanelConfig ();

################################################################################
# Set up output handling code

################################################################################

sub ssystem {
    my @cmd     = @_;
    my $conf_hr = ref( $cmd[-1] ) eq 'HASH' ? pop(@cmd) : {};
    die "no command line passed to ssystem!" unless @cmd;

    no warnings 'redefine';
    local *DEBUG = *DEBUG;
    *DEBUG = sub { }
      if $conf_hr->{'quiet'};

    local $CpanelLogger::message_caller_depth = $CpanelLogger::message_caller_depth + 1;    # Set caller depth deeper during this sub so debugging it clearer.
    DEBUG( '- ssystem [BEGIN]: ' . join( ' ', @cmd ) );
    open( my $rnull, '<', '/dev/null' ) or die "Can't open /dev/null: $!";
    my $io  = IO::Handle->new;
    my $pid = IPC::Open3::open3( $rnull, $io, $io, @cmd );
    $io->blocking(0);

    my $select = IO::Select->new($io);

    my $exit_status;
    my $buffer                 = '';
    my $buffered_waiting_count = 0;
    while ( !defined $exit_status ) {
        while ( my $line = readline($io) ) {

            # Push the buffer lacking a newline onto the front of this.
            if ($buffer) {
                $line   = $buffer . $line;
                $buffer = '';
            }

            $line =~ s/\r//msg;    # Strip ^M from output for better log output.

            # Internally buffer on newlines.
            if ( $line =~ m/\n$/ms ) {
                DEBUG( "  " . $line );
                $buffered_waiting_count = 0;
            }
            else {
                print "." if ( $buffered_waiting_count++ > 1 );
                $buffer = $line;
            }
        }

        # Parse exit status or yield time to the CPU.
        if ( waitpid( $pid, 1 ) == $pid ) {
            DEBUG( "  " . $buffer ) if $buffer;
            $exit_status = $? >> 8;
        }
        else {

            # Watch the file handle for output.
            $select->can_read(0.01);
        }
    }

    if ($exit_status) {
        if ( !$conf_hr->{'ignore_errors'} ) {
            ERROR("  - ssystem [EXIT_CODE] '$cmd[0]' exited with $exit_status");
        }
        else {
            DEBUG("  - ssystem [EXIT_CODE] '$cmd[0]' exited with $exit_status (ignored)");
        }
    }

    close($rnull);
    $io->close();

    DEBUG('- ssystem [END]');

    return $exit_status;
}

sub check_file_is_valid_xz {
    my ($file) = @_;

    open( my $fh, '<:raw', $file ) or die "Cannot open file '$file': $!";

    # read the first 6 bytes (the magic number)
    my $magic_number;
    my $bytes_read = read( $fh, $magic_number, 6 );

    # check for incomplete downloads
    if ( defined $bytes_read && $bytes_read == 6 ) {

        # seek to 2 bytes from the end of the file
        seek( $fh, -2, 2 );

        my $footer;
        my $footer_read = read( $fh, $footer, 2 );

        close($fh);

        return $footer_read == 2 && $magic_number eq "\xFD\x37\x7A\x58\x5A\x00" && $footer eq "\x59\x5A";
    }

    return 0;
}

sub cpfetch {
    my ( $url, %opts ) = @_;

    if ( !$url ) {
        FATAL("The system called the cpfetch process without a URL.");
    }

    my $file = _get_file( $url, %opts );
    return unless defined $file;

    if ( $file =~ /\.bz2$/ ) {
        ssystem( "/usr/bin/bunzip2", $file );
    }

    if ( CpanelConfig::signatures_enabled() ) {
        $url  =~ s/\.bz2$//g;
        $file =~ s/\.bz2$//g;

        my $sig = _get_file("$url.asc");
        CpanelGPG::verify_file( $file, $sig, $url );
    }

    if ( $file =~ /\.xz$/ ) {
        if ( check_file_is_valid_xz($file) ) {
            ssystem( "/usr/bin/unxz", $file );
        }
        else {
            FATAL(
                'The downloaded file is not in the expected format.

  Ensure that the URLs in /etc/cpsources.conf are valid mirrors.'
            );
        }
    }

    return;
}

sub _get_file {
    my ( $url, %opts ) = @_;

    $url = 'http://' . CpanelConfig::source() . $url;

    # NOTE: assumes no query or fragment in URL
    my @FILE = split( /\//, $url );
    my $file = pop(@FILE);

    if ( -e $file ) {
        WARN("Warning: Overwriting the $file file...");
        unlink $file;
        FATAL("The system could not remove the $file file.") if ( -e $file );
    }

    DEBUG("Retrieving $url to the $file file...");
    my ( $rc, $out ) = fetch_url_to_file( $url, $file );

    if ( !-e $file || -z $file ) {
        unlink $file;
        FATAL("The system could not fetch the $file file: $out");
    }

    return $file;
}

# $file can be '-' to return the output as a scalar, or the path to a file to download the given $url
sub fetch_url_to_file {
    my ( $url, $file ) = @_;

    # wget fallback for our single https call. RHEL 6 does not have a new enough SSL stack.
    if ( $url =~ m/^https/i && HTTP::Tiny->can_ssl == 0 ) {
        unlink $file;
        ssystem( qw{/usr/bin/wget --no-verbose --inet4-only --output-document}, $file, $url );
        return ( 1, 'ok' ) if $? == 0 && -s $file;
    }

    my $data_callback = sub {
        my ( $data, $progress ) = @_;

        open( my $fh, '>>', $file ) or die("Cannot open $file for write");
        binmode $fh;
        print {$fh} $data;
        close $fh;
    };

    my $download_failure_reason = '';

    my $max = 3;
    foreach my $iter ( 1 .. $max ) {
        unlink $file;
        my $http     = HTTP::Tiny->new;
        my $response = $http->get( $url, { 'data_callback' => $data_callback } );
        return ( 1, 'ok' ) if $response && $response->{'success'};

        $download_failure_reason = sprintf( "%s: %s", $response->{'status'} // 0, $response->{'reason'} // "unknown failure" );
        WARN("Call to URL '$url' failed, attempt [$iter/$max]");
        if ( $iter == $max ) {

            # If HTTP::Tiny did not work, try with wget
            # wget has better handling for failed mirrors
            ssystem( qw{/usr/bin/wget --no-verbose --inet4-only --output-document}, $file, $url );
            return ( 1, 'ok' ) if $? == 0 && -s $file;

            my $type = substr( $url, -4 ) eq '.asc' ? 'signature' : 'file';
            if ( $download_failure_reason =~ /^[4-5]\d\d/ ) {    # response code in 400s or 500s
                FATAL(
                    "Failed to download $type at URL $url: $download_failure_reason

  Ensure that the URLs in /etc/cpsources.conf are valid mirrors
  and that the version number set in /etc/cpupdate.conf is a valid
  version number."
                );
            }
            else {
                FATAL("Failed to download $type at URL $url: $download_failure_reason");
            }
        }
        sleep 3;
    }

    return ( 0, $download_failure_reason );
}

1;
