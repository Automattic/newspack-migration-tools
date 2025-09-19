# Coding Conventions and Standards

Please follow these coding conventions and standards when developing within this repository.

## Avoid Cascading Constructors

Do not instantiate a bunch of helpers inside a migrator constructor - instead, only instantiate helpers in the command functions when needed.  This will help to mitigate an entire migrator from becoming unusable if a single helper has a bug or has it's own dependecies that are not needed for the entire migrator to work.

```
// Bad:
public function __construct() {
   $this->helper1 = new Helper1();
   $this->helper2 = new Helper2();
   ....
}

// Good:
public function __construct() {}
public function cmd_my_command( array $pos_args, array $assoc_args ) {
    $this->helper1 = new Helper1();
}
```

## Plugin Dependency Validation

When checking if a plugin is active, it is OK to put the `is_plugin_active()` check directly in the helper's constructor, but it’s also OK to put the check in it’s own function instead and only validate as needed. This might be helpful if a plugin doesn’t need to be active in order to use some of the helper's functions.  Another option could be to add a boolean argument to the constructor to bypass the `is_plugin_active()` if needed.

```
// Option 1:
public function __construct() {
   if ( ! is_plugin_active( ... ) ) NMT::exit_with_message( ... );
}

// Option 2:
public function __construct() {}
public function check_plugin_active() {
   if ( ! is_plugin_active( ... ) ) NMT::exit_with_message( ... );
}
public function do_work() {
   $this->check_plugin_active();
   ...
}

// Option 3:
public function __construct( $do_active_check = true ) {
   if ( $do_active_check ) $this->check_plugin_active();
}
public function check_plugin_active() {
   if ( ! is_plugin_active( ... ) ) NMT::exit_with_message( ... );
}
public function do_work_1() {
   $this->check_plugin_active();
   ...
}
public function do_work_2() {
   ...
}
```






