#!/usr/local/core/bin/python
import os.path
import sys
import django
sys.path.append('/usr/local/core')
os.environ.setdefault("DJANGO_SETTINGS_MODULE", "core.settings")
django.setup()
from loginSystem.models import Administrator, ACL

def main():
    admin = Administrator.objects.get(userName='admin')
    admin.api = 1
    admin.save()

    print("API Access Enabled")

if __name__ == "__main__":
    main()