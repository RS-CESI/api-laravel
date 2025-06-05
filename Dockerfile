FROM ubuntu:latest
LABEL authors="cguer"

ENTRYPOINT ["top", "-b"]
