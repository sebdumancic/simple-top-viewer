#!/usr/bin/python
# gather linux system statistics and output in html/php for this machine

#import commands
from subprocess import *
import sys
import getpass
import os
import time
import csv

NUMPROCS=9
DIR="/home/seba/public_html/public_html/pinacs"
EXT="dat"

########

output = ""

# uptime information:
uptime = Popen("uptime", shell=True, stdout=PIPE, stderr=STDOUT, close_fds=True).communicate()[0].strip()
output += "<tr><td colspan=\"6\">%s</td></tr>"%uptime
load = uptime.split('load average: ')[1].split(', ')

procs = Popen("ps axw -o user:25,nice,pcpu,pmem,etime,args --sort -pcpu | head -n %i"%(NUMPROCS+3), shell=True, stdout=PIPE, stderr=STDOUT, close_fds=True).communicate()[0].strip().split("\n")
#p = Popen("top -c -b -n1 | head -n14", shell=True, stdout=PIPE, stderr=STDOUT, close_fds=True)
#procs = p.stdout.readlines()
#procs = commands.getoutput("top -b -n1 | head -15").split("\n")

totcpu = 0
totmem = 0
users = []
nprocs = 0
for proc in procs:
  # only display and count NUMPROCS processes
  if nprocs > NUMPROCS:
    break

  d = [x for x in proc.strip().split(" ") if x != '']
  #dd = [x for x in procs[i].strip().split(" ") if x != '']
  #cols = [1,3,8,9,10,11]
  #d = [dd[i] for i in cols]

  this_user = getpass.getuser()
  this_script = os.path.abspath(__file__)
  # ignore 0% cpu comands and cron, plymouth as well as this script
  if (d[2] != '%CPU' and float(d[2]) == 0) or \
     (d[0] == 'root' and d[5] == '/USR/SBIN/CRON') or \
     (d[0] == 'root' and d[5] == 'CRON') or \
     d[5] == '/sbin/plymouthd' or \
     d[5] == '/usr/sbin/unity-greeter' or \
     (d[0] == this_user and d[5] == 'crond') or \
     (d[0] == this_user and d[5] == '[head]') or \
     (d[0] == this_user and d[5] == 'head' and d[6] == '-n') or \
     (d[0] == this_user and d[5] == 'ps' and d[6] == 'axw') or \
     (d[0] == this_user and d[5] == '/bin/sh' and d[7] == 'ps' and d[8] == 'axw') or \
     d[-1] == this_script or \
     (d[5] == '/usr/bin/python' and d[6] == this_script):
    continue

  # busy process in bold
  if d[2] != '%CPU' and float(d[2]) >= 50:
    d[0] = "<b>%s</b>"%d[0]

  # condor in italic
  if d[0] == "condor":
    d[0] = "<i>%s</i>"%d[0]

  # root and the header are not real people
  if not (d[0] in ("root","USER")):
    users.append(d[0])

  output += "<tr><td>%s</td><td>%s</td></tr>"%("</td><td>".join(d[0:5]), " ".join(d[5:]))
  nprocs += 1

  try:
    totcpu += float(d[2])
    totmem += float(d[3])
  except ValueError:
    continue

# divide by number of cpus
ncpus = os.sysconf("SC_NPROCESSORS_ONLN")
totcpu = totcpu/ncpus

p = Popen("hostname", shell=True, stdout=PIPE, close_fds=True)
hostname = p.stdout.readline().strip().lower()
#hostname = commands.getoutput("hostname")


query_attributes = ['index','name','utilization.gpu','utilization.memory','memory.total','memory.free','memory.used']
gpu_info = dict()
gpus = list()
for att in query_attributes:
    gpu_info[att]=dict()

###GPU
try:
    p2 = Popen(['nvidia-smi', '--query-gpu='+','.join(query_attributes), '--format=csv'], stdout=PIPE, close_fds=True)
    gpu_csv = csv.reader(p2.stdout, skipinitialspace=True)
    headers = gpu_csv.next()
    for row in gpu_csv:
        index = row[0]
        name = hostname+'_'+index
        gpus.append(name)
        for i,att in enumerate(query_attributes):
            gpu_info[att][name] = row[i]
    
except Exception as e:
    print(e)
    pass
###
print(gpu_info)
#query_attributes = [s.replace('.','') for s in query_attributes]

output = "<tr><td colspan=\"6\"><b>%s</b> (CPU:%s%% - MEM:%s%%)</td></tr>"%(hostname, totcpu,totmem)+output

f = open("%s/%s.%s"%(DIR,hostname,EXT), "w")
f.write("<?php\n")
f.write("$cpu['%s'] = %.1f;\n"%(hostname,totcpu))
f.write("$gpu['{}'] = array('{}');\n".format(hostname,"', '".join(gpus)))
f.write("$mem['%s'] = %.1f;\n"%(hostname,totmem))
f.write("$load['%s'] = array('%s');\n"%(hostname,"', '".join(load)))
f.write("$users['%s'] = array('%s');\n"%(hostname,"', '".join(users)))
f.write("$time['%s'] = %s;\n"%(hostname, time.time()))
f.write("$output['%s'] = '%s';\n"%(hostname,output.encode('string_escape')))
for att in query_attributes:
    for gpu in gpus:
        f.write("${}['{}'] = '{}';\n".format(att.replace('.',''),gpu,gpu_info[att][gpu]))
f.write("?>")
f.close()

