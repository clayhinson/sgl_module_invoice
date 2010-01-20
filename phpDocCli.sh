#!/bin/bash
# $Id: phpDocCli.sh,v 1.5 2005/05/31 22:02:50 demian Exp $

#/**
#  * makedoc - PHPDocumentor script to save your settings
#  *
#  * Put this file inside your PHP project homedir, edit its variables and run whenever you wants to
#  * re/make your project documentation.
#  *
#  * The version of this file is the version of PHPDocumentor it is compatible.
#  *
#  * It simples run phpdoc with the parameters you set in this file.
#  * NOTE: Do not add spaces after bash variables.
#  *
#  * @copyright         makedoc.sh is part of PHPDocumentor project {@link http://freshmeat.net/projects/phpdocu/} and its LGPL
#  * @author            Roberto Berto <darkelder (inside) users (dot) sourceforge (dot) net>
#  * @version           Release-1.1.0
#  */


##############################
# should be edited
##############################

#/**
#  * title of generated documentation, default is 'Generated Documentation'
#  *
#  * @var               string TITLE
#  */
TITLE="Invoice Module"

#/**
#  * name to use for the default package. If not specified, uses 'default'
#  *
#  * @var               string PACKAGES
#  */
PACKAGES="Invoice"

#/**
#  * name of a directory(s) to parse directory1,directory2
#  * $PWD is the directory where makedoc.sh
#  *
#  * @var               string PATH_PROJECT
#  */
PATH_PROJECT=$PWD/classes

#/**
#  * name of a file(s) to parse file1,file2
#  *
#  * @var               string FILES
#  */

#/**
#  * path of PHPDoc executable
#  *
#  * @var               string PATH_PHPDOC
#  */
PATH_PHPDOC=/usr/bin/phpdoc

#/**
#  * where documentation will be put
#  *
#  * @var               string PATH_DOCS
#  */
PATH_DOCS=/Sites/documentation/modules/invoice

#/**
#  * what outputformat to use (html/pdf)
#  *
#  * @var               string OUTPUTFORMAT
#  */
OUTPUTFORMAT=HTML

#/**
#  * converter to be used
#  *
#  * @var               string CONVERTER
#  */
CONVERTER=frames

#/**
#  * template to use
#  *
#  * @var               string TEMPLATE
#  */
TEMPLATE=earthli

#/**
#  * parse elements marked as private
#  *
#  * @var               bool (on/off)           PRIVATE
#  */
PRIVATE=on

rm -fr $PATH_DOCS
mkdir $PATH_DOCS
# make documentation
$PATH_PHPDOC -d $PATH_PROJECT -t $PATH_DOCS -ti "$TITLE" -dn $PACKAGES -o $OUTPUTFORMAT:$CONVERTER:$TEMPLATE -pp $PRIVATE --ignore "tests/"


# vim: set expandtab :
