UMAN = user_manual

TEXS = $(UMAN).tex
FIGURES = frontside.png frontone.png EditPatronInfo.png PatronManagement.png \
	  ColectionListing.png AddItem.png AmazonSearch.png \
	  AmazonSearchResults.png AmazonLookupResults.png \
	  CirculationTypesList.png CirculationTypesStatsList.png \
	  MainCirculation.png ItemCirculationRecord.png \
	  PatronCirculationRecord.png CirculationHoldList.png \
	  CirculationCheckoutList.png CirculationCheckinPage.png \
	  FindPatron.png

EXTRA_DIST = $(TEXS) $(FIGURES)

PDF = $(UMAN).pdf

noinst_DATA=$(PDF)

$(PDF) : $(TEXS) $(FIGURES)
	$(PDFLATEX) $(UMAN)
	$(PDFLATEX) $(UMAN)
	$(PDFLATEX) $(UMAN)


