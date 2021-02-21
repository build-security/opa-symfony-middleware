package symfony.authz

import data.datasources.RBAC

default allow = false

# Find the user, or set to default user if not found.

default user = {
  "id": 0,
  "role": "anyone",
}

user = x {
  x := RBAC.users[username]
  username == input.request.headers.user[0]
}

# Extract role based on user

role := RBAC.roles[user.role]

# Collect all permissions for this role into a single list,
# including permissions from sub-roles.

sub_role_perm_list := [RBAC.roles[subrole].permissions | subrole = role.sub_roles[_]]

sub_role_permissions = x {  
  x = [perm | perm = sub_role_perm_list[_][_]]
}

permissions := array.concat(role.permissions, sub_role_permissions)

# We need to apply some special logic if this request needs
# member-level permissions. This rule evaluates to true if any
# of the requirements are member-level.

requires_member_permissions {
  perm := input.resources.requirements[_]
  perm == RBAC.roles[rolename].permissions[_]
  rolename == "member"
}

# Make decision: authz should not be allowed if:
#  - Any of the requirements in the input are not available in this user's permissions
#  - This user has role member and is requesting access to another member's resource

any_requirements_not_match {
  count(input.resources.requirements) != count([1 | req = input.resources.requirements[_]; req == permissions[_]])
}

allow {
  not any_requirements_not_match
  
  # If the user is a member, make sure they are making changes
  # to their own resource.
  user.role == "member"
  requires_member_permissions
  
  input.request.headers.user[0] == input.resources.attributes.user
}

allow {
  not any_requirements_not_match
  
  user.role != "member"
}