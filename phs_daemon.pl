#!/usr/bin/perl

use warnings;
use strict;
use POE;               # deb libpoe-perl
use Linux::Inotify2;   # deb liblinux-inotify2-perl
use Cache::Memcached;  # deb libcache-memcached-perl

#FIXME: common config
my $memc_host = '127.0.0.1';
my $memc_port = '11211';
my $session_path = '/var/lib/php5';

# Init memc object
my $memc = new Cache::Memcached;
$memc->set_servers([ "$memc_host:$memc_port" ]);

# Init inotify object
my $inotify = new Linux::Inotify2
        or die "unable to create Inotify object: $?";
$inotify->blocking(0);
my $watcher = $inotify->watch($session_path, IN_CLOSE_WRITE | IN_DELETE | IN_ATTRIB);

POE::Session->create(
  'inline_states' => {
    '_start' => \&start,
    'ev'     => \&ev_process,

  }
);

sub start {
  print "starting\n";
  my $kernel = $_[KERNEL];
  open my $inotify_FH, "< &=" . $inotify->fileno or die "Can’t fdopen: $!\n";
  $kernel->sig('HUP','sighup');
  $kernel->select_read($inotify_FH,'ev');
}

sub ev_process {
  my @events = $inotify->read;
  unless (@events > 0) {
    print "read error: $!";
    last;
  }

  foreach my $ev (@events) {
    my $name = $ev->name;
    next unless $name =~ m/^php_sess_[a-z0-9]+/;

    if($ev->IN_CLOSE_WRITE or $ev->IN_ATTRIB) {
      # session content somehow changed
      my $c = '';
      open SESS, $session_path.'/'.$name;
      while(<SESS>) { $c .= $_; }
      close SESS;
      if($c) {
        print "write to $name: $c\n";
        $memc->set($name,$c);
      }
    }
    elsif($ev->IN_DELETE) {
      $memc->delete($name);
      print "del $name\n"
    }
  }

}

$poe_kernel->run;