<?php
namespace app;


class constants {

	// Roles are gated on routes via a route's requiredRoles, so they belong in one place
	// the route table can name rather than as string literals spread through it.
	public const string ROLE_WIDGET_READ = 'Widget.Read';

	public const string ROLE_WIDGET_WRITE = 'Widget.Write';

}
