# Custom Admin Configuration for CyberPanel
from django.contrib import admin
from django.contrib.admin import AdminSite
from django.template.response import TemplateResponse
from django.urls import path
from django.conf import settings
import os

class AdminiAdminSite(AdminSite):
    site_header = "Admini Admin"
    site_title = "Admini Admin"
    index_title = "Welcome to Admini Administration"
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.site_header = "Admini Admin"
        self.site_title = "Admini Admin"
        self.index_title = "Welcome to Admini Administration"
    
    def index(self, request, extra_context=None):
        """
        Display the main admin index page, which lists all of the installed
        apps that have been registered in this admin site.
        """
        app_list = self.get_app_list(request)
        
        context = {
            **self.each_context(request),
            'title': self.index_title,
            'app_list': app_list,
            'has_permission': self.has_permission(request),
        }
        
        # Add custom context for modern styling
        context.update({
            'modern_admin_css': '/static/admin/css/modern-admin.css',
            'google_loader': True,
        })
        
        context.update(extra_context or {})
        
        return TemplateResponse(
            request,
            self.index_template or 'admin/index.html',
            context,
        )
    
    def login(self, request, extra_context=None):
        """
        Display the login form for the given HttpRequest.
        """
        if request.method == 'GET' and self.has_permission(request):
            # Already logged-in, redirect to admin index
            index_path = reverse('admin:index', current_app=self.name)
            return HttpResponseRedirect(index_path)
        
        from django.contrib.auth.views import LoginView
        from django.urls import reverse
        
        # Add custom context for modern login styling
        extra_context = extra_context or {}
        extra_context.update({
            'modern_admin_css': '/static/admin/css/modern-admin.css',
            'google_loader': True,
        })
        
        return LoginView.as_view(
            template_name='admin/login.html',
            extra_context=extra_context,
        )(request)

# Create custom admin site instance
admini_admin = AdminiAdminSite(name='admini_admin')

# Register models with the custom admin site
from django.contrib.auth.models import User, Group
from loginSystem.models import Administrator
from databases.models import Databases
from websiteFunctions.models import Websites
from packages.models import Package
from userManagment.models import Administrator as UserAdmin

# Register default models
admini_admin.register(User)
admini_admin.register(Group)

# Register Admini specific models
try:
    admini_admin.register(Administrator)
except:
    pass

try:
    admini_admin.register(Databases)
except:
    pass

try:
    admini_admin.register(Websites)
except:
    pass

try:
    admini_admin.register(Package)
except:
    pass

try:
    admini_admin.register(UserAdmin)
except:
    pass