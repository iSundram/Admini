package CpanelLogger;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.



use strict;
use warnings;

require Exporter;
our @EXPORT = qw(DEBUG ERROR WARN INFO FATAL begin_collect_output emit_collected_output);    ## no critic qw(ProhibitAutomaticExportation)
our @ISA    = qw(Exporter);

use constant COLOR_RED           => 31;
use constant COLOR_YELLOW        => 33;
use constant DISABLE_COLORS_FILE => '/var/cpanel/disable_cpanel_terminal_colors';

use constant LOG_FILE => '/var/log/cpanel-install.log';
our $log_fh;

# Helper routines for the log.
our $message_caller_depth = 1;

my $collect_output;

sub colorize_bold {
    my ( $color, $msg ) = @_;

    return $msg if !defined $color || -e DISABLE_COLORS_FILE;
    $msg ||= '';

    return chr(27) . '[1;' . $color . 'm' . $msg . chr(27) . '[0;m';
}

# space pad debug messages.
sub DEBUG { my ($msg) = @_; return _MSG( 'DEBUG', "  " . $msg ) }
sub ERROR { my ($msg) = @_; return _MSG( 'ERROR', colorize_bold( COLOR_RED,    $msg ) ) }
sub WARN  { my ($msg) = @_; return _MSG( 'WARN',  colorize_bold( COLOR_YELLOW, $msg ) ) }
sub INFO  { my ($msg) = @_; return _MSG( 'INFO',  $msg ) }
sub FATAL { my ($msg) = @_; print_support_info(); _MSG( 'FATAL', colorize_bold( COLOR_RED, $msg ) ); die "\n"; }

sub print_support_info {
    INFO("cPanel is here to help! Our technical KnowledgeBase and cPanel Community is just a click away at https://support.cpanel.net/");
    return;
}

sub _MSG {
    my ( $level, $msg ) = @_;
    $msg ||= '';
    chomp $msg;

    my ( $sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst ) = localtime;
    my ( $package, $filename, $line ) = caller($message_caller_depth);

    my $stamp_msg = sprintf( "%04d-%02d-%02d %02d:%02d:%02d %4s [%d] (%5s): %s\n", $year + 1900, $mon + 1, $mday, $hour, $min, $sec, $line, $$, $level, $msg );

    print {$log_fh} $stamp_msg;
    if ( defined $collect_output ) {
        $collect_output .= $stamp_msg;
    }
    else {
        print $stamp_msg;
    }
    return;
}

sub begin_collect_output {
    $collect_output = '';

    return;
}

sub emit_collected_output {
    print $collect_output;
    undef $collect_output;
    return;
}

sub close_log_file {
    close $log_fh;
    return;
}

sub open_log_for_append {
    my $orig_umask = umask(0077);

    # Re-open the log regardless of success.
    open( $log_fh, '>>', LOG_FILE ) or die "Can't open log file: $!";
    $log_fh->autoflush(1);

    umask($orig_umask);
    return;
}

sub open_logs {
    if ( my $mtime = ( stat(LOG_FILE) )[9] ) {
        my $bu_file = LOG_FILE . '.' . $mtime;
        system( '/bin/cp', LOG_FILE, $bu_file ) unless -e $bu_file;
    }

    open_log_for_append();

    my $install_start      = time();
    my $install_start_time = localtime($install_start);
    INFO("cPanel & WHM installation started at: $install_start_time!");
    INFO("This installation will require 10-50 minutes, depending on your hardware and network.");
    INFO("Now is the time to go get another cup of coffee/jolt.");
    INFO("The install will log to the /var/log/cpanel-install.log file.");
    INFO("");
    INFO("Beginning Installation v3...");

    return $install_start;
}

1;
