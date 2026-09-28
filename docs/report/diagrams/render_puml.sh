#!/bin/zsh
# Renders the PlantUML diagram sources (puml/*.puml) to docs/report/figures/diagrams/<name>.png.
# Needs Java and plantuml.jar (MIT build, https://github.com/plantuml/plantuml/releases):
#   JAVA=/path/to/java PLANTUML_JAR=/path/to/plantuml.jar docs/report/diagrams/render_puml.sh [name.puml ...]
here=${0:A:h}
java=${JAVA:-java}
jar=${PLANTUML_JAR:?set PLANTUML_JAR to the plantuml.jar path}
cd "$here/puml"
files=(${@:-*.puml})
"$java" -Djava.awt.headless=true -jar "$jar" -charset UTF-8 -tpng -o "$here/../figures/diagrams" $files
for f in $files; do echo "${f:t} -> docs/report/figures/diagrams/${f:t:r}.png"; done
