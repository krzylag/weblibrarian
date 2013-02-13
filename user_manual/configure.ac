AC_INIT(WebLibrarian,3.0)
AC_CONFIG_SRCDIR(user_manual.tex)
AM_INIT_AUTOMAKE
AC_CONFIG_FILES([Makefile])

if test "x${prefix}" = "xNONE"; then
    prefix=/usr/local
fi
if test "x${exec_prefix}" = "xNONE"; then
    exec_prefix="${prefix}"
fi

BUILD_DIR=`pwd`
TOP_DIR=${BUILD_DIR}

AC_SUBST(TOP_DIR)
AC_SUBST(BUILD_DIR)

AC_PATH_PROGS(PDFLATEX,pdflatex)
AC_PATH_PROG(MAKEINDEX,makeindex,/bin/true)
AC_PATH_PROG(BIBTEX,bibtex,/bin/true)
AC_PATH_PROGS(HTLATEX,[htlatex htlatex.sh],/bin/true)

AC_OUTPUT


