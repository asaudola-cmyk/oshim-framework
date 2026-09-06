PHP_ARG_ENABLE([oshim],
  [whether to enable the Sovereign OSHIM Native C SAPI Engine],
  [AS_HELP_STRING([--enable-oshim],
    [Enable building the Sovereign OSHIM Bare-Metal C SAPI Engine])],
  [yes],
  [no])

AC_MSG_CHECKING(for Sovereign OSHIM C SAPI build)
if test "$PHP_OSHIM" != "no"; then
  PHP_ADD_MAKEFILE_FRAGMENT($abs_srcdir/sapi/oshim/Makefile.frag)

  dnl Output binary target location
  SAPI_OSHIM_PATH=sapi/oshim/oshim

  dnl Register SAPI in build system with static TSRMLS cache
  PHP_SELECT_SAPI(oshim, program, oshim.c, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1, '$(SAPI_OSHIM_PATH)')
  AC_MSG_RESULT([yes])
else
  AC_MSG_RESULT([no])
fi
